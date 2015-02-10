<?php

/**
 * @file
 * Contains Drupal\flippy\FlippyPager.
 */

namespace Drupal\flippy;

/**
 * Defines the flippy pager service.
 */
class FLippyPager {

  /**
   * Helper function: Query to get the list of flippy pagers.
   *
   * @parameter
   *   current node object
   *
   * @return
   *   a list of flippy pagers
   */
  public static function flippy_build_list($node) {
    // Get all the properties from the current node

    $master_list = &drupal_static(__FUNCTION__);
    if (!isset($master_list)) {
      $master_list = array();
    }
    if (!isset($master_list[$node->id()])) {
      // Check to see if we need custom sorting
      if (\Drupal::config('flippy.settings')->get('flippy_custom_sorting_' . $node->getType())) {
        // Get order
        $order = \Drupal::config('flippy.settings')->get('flippy_order_' . $node->getType());
        // Get sort
          $sort = \Drupal::config('flippy.settings')->get('flippy_sort_' . $node->getType());
      }
      else {
        $order = 'ASC';
        $sort = 'created';
      }
      // Validate that the sort criteria is OK to use
      $base_table_properties = array_keys(_flippy_sorting_properties());
      $field_value = NULL;
      // If the sort criteria is not in the base_table_properties array,
      // we assume it's a field
      if (!in_array($sort, $base_table_properties)) {
        // get the value of the current node's field (use the first one only)
        $current_field_items = $node->{$sort}->getValue();
        if (!isset($current_field_items[0]['value'])) {
          // should never happen, but just in case, fall back to post date ascending
          $sort  = 'created';
          $order = 'ASC';
        }
        else {
          // Otherwise save the field value for later
          $field_value = $current_field_items[0]['value'];
        }
      }
      // Depending on order, decide what before and after means
      $before = ($order == 'ASC') ? '<' : '>';
      $after  = ($order == 'ASC') ? '>' : '<';
      // Also decide what up and down means
      $up   = ($order == 'ASC') ? 'ASC' : 'DESC';
      $down = ($order == 'ASC') ? 'DESC' : 'ASC';
      // Create a starting-point SelectQuery object
      // todo: convert the SelectQuery into EntityQuery when D8 EntityQuery start
      // todo: to support nested conditions.
      //$language = new Language();
      $query = db_select('node_field_data', 'nfd');
      $query->fields('nfd', array('nid'))
        ->condition('nfd.type', $node->getType())
        ->condition('nfd.status', 1)
        ->condition('nfd.nid', $node->id(), '!=')
        //todo: add language condition.
        //->condition('langcode', array($language::LANGCODE_DEFAULT, $language::LANGCODE_NOT_SPECIFIED), 'IN')
        ->range(0, 1)
        ->addTag('node_access');
      // Create the individual queries
      $first  = clone $query;
      $prev   = clone $query;
      $next   = clone $query;
      $last   = clone $query;
      $random = clone $query;
      // We will construct the queries differently depending on whether the sorting
      // criteria is a field or a base table property.
      // If we found a field value earlier, we know we're dealing with a field
      if ($field_value) {
        // set the conditions

        // first and last query
        $first->leftjoin('node__' . $sort, $sort, 'nfd.nid = ' . $sort . '.entity_id');
        $first->condition(db_or()
            ->condition($sort . '.' . $sort . '_value', $field_value, $before)
            ->condition(db_and()
                ->condition('nfd.nid', $node->id(), $before)
                ->condition(db_or()
                    ->condition($sort . '.' . $sort . '_value', $field_value, '=')
                    ->isnull($sort . '.' . $sort . '_value')
                )
            )
        );

        $last->leftjoin('node__' . $sort, $sort, 'nfd.nid = ' . $sort . '.entity_id');
        $last->condition(db_or()
            ->condition($sort . '.' . $sort . '_value', $field_value, $after)
            ->condition(db_and()
                ->condition('nfd.nid', $node->id(), $after)
                ->condition(db_or()
                    ->condition($sort . '.' . $sort . '_value', $field_value, '=')
                    ->isnull($sort . '.' . $sort . '_value')
                )
            )
        );

        // previous query to find out the previous item based on the field, using
        // node id if the other criteria is the same.
        $prev->leftjoin('node__' . $sort, $sort, 'nfd.nid = ' . $sort . '.entity_id');
        $prev->condition(db_or()
            ->condition($sort . '.' . $sort . '_value', $field_value, $before)
            ->condition(db_and()
                ->condition('nfd.nid', $node->id(), $before)
                ->condition(db_or()
                    ->condition($sort . '.' . $sort . '_value', $field_value, '=')
                    ->isnull($sort . '.' . $sort . '_value')
                )
            )
        );
        $next->leftjoin('node__' . $sort, $sort, 'nfd.nid = ' . $sort . '.entity_id');
        $next->condition(db_or()
            ->condition($sort . '.' . $sort . '_value', $field_value, $after)
            ->condition(db_and()
                ->condition('nfd.nid', $node->id(), $after)
                ->condition(db_or()
                    ->condition($sort . '.' . $sort . '_value', $field_value, '=')
                    ->isnull($sort . '.' . $sort . '_value')
                )
            )
        );

        // set the ordering
        $first->orderBy($sort . '.' . $sort . '_value', $up);
        $prev->orderBy($sort . '.' . $sort . '_value', $down);
        $next->orderBy($sort . '.' . $sort . '_value', $up);
        $last->orderBy($sort . '.' . $sort . '_value', $down);
      }
      else {
        // Otherwise we assume the variable is a column in the base table
        // (a property). Like above, set the conditions

        $sort_value = $node->get($sort);
        $sort_value = $sort_value->getValue();
        // first and last query
        $first->condition($sort, $sort_value[0]['value'], $before);
        $last->condition($sort, $sort_value[0]['value'], $after);

        // previous query to find out the previous item based on the field, using
        // node id if the other criteria is the same.
        $prev->condition(db_or()
            ->condition($sort, $sort_value[0]['value'], $before)
            ->condition(db_and()
                ->condition($sort, $sort_value[0]['value'], '=')
                ->condition('nfd.nid', $node->id(), $before)
            )
        );

        // next query to find out the next item based on the field, using
        // node id if the other criteria is the same.
        $next->condition(db_or()
            ->condition($sort, $sort_value[0]['value'], $after)
            ->condition(db_and()
                ->condition($sort, $sort_value[0]['value'], '=')
                ->condition('nfd.nid', $node->id(), $after)
            )
        );

        // set the ordering
        $first->orderBy($sort, $up);
        $prev->orderBy($sort, $down);
        $next->orderBy($sort, $up);
        $last->orderBy($sort, $down);
      }

      // set the secondary ordering in case the values are the same
      $first->orderBy('nfd.nid', $up);
      $prev->orderBy('nfd.nid', $down);
      $next->orderBy('nfd.nid', $up);
      $last->orderBy('nfd.nid', $down);

      // Execute the queries
      $results = array();
      $results['first'] = $first->execute()->fetchField();
      $results['prev']  = $prev->execute()->fetchField();
      $results['next']  = $next->execute()->fetchField();
      $results['last']  = $last->execute()->fetchField();

      $node_ids = array();
      foreach ($results as $key => $result) {
        // if the query returned no results, it means we're already
        // at the beginning/end of the pager, so ignore those
        if (count($result) > 0) {
          // otherwise we save the node ID
          $node_ids[$key] = $results[$key];
        }
      }

      // make our final array of node IDs and titles
      $list = array();
      // but only if we actually found some matches
      if (count($node_ids) > 0) {
        // we also need titles to go with our node ids
        $title_query = db_select('node_field_data', 'nfd')
          ->fields('nfd', array('title', 'nid'))
          ->condition('nfd.nid', $node_ids, 'IN')
          ->execute()
          ->fetchAllAssoc('nid');

        foreach ($node_ids as $key => $nid) {
          $list[$key] = array(
            'nid' => $nid,
            'title' => isset($title_query[$nid]) ? $title_query[$nid]->title : '',
          );
        }
      }
      // create random list
      if (\Drupal::config('flippy.settings')->get('flippy_random_' . $node->getType())) {
        $random->orderRandom();
        $random_nid = $random->execute()->fetchField();

        // find out the node title
        $title = db_select('node_field_data', 'nfd')
          ->fields('nfd', array('title'))
          ->condition('nfd.nid', $random_nid, '=')
          ->execute()
          ->fetchField();
        $list['random'] = array(
          'nid' => $random_nid,
          'title' => $title
        );
      }
      // finally set the current info for themers to use

      $list['current'] = array(
        'nid' => $node->id(),
        'title' => $node->getTitle(),
      );

      $master_list[$node->id()] = $list;
    }
    return $master_list[$node->id()];
  }
}
?>
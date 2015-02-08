<?php
/**
* @file
* Contains \Drupal\flippy\Plugin\Block\FlippyBlock.
*/

namespace Drupal\flippy\Plugin\Block;

use Drupal\block\BlockBase;
use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a "Flippy" block.
 *
 * @Block(
 *   id = "flippy_block",
 *   admin_label = @Translation("Flippy Block")
 * )
 */
class FlippyBlock extends BlockBase {

  /**
   * Implements \Drupal\block\BlockBase::build().
   */
  public function build() {
    $build = array();
    // Detect if we're viewing a node
    if ($node = menu_get_object('node')) {
      // Make sure this node type is still enabled
      if (_flippy_use_pager($node)) {
        // Generate the block
        $build['#children'] = theme('flippy', array('list' => flippy_build_list($node)));
        // Set head elements
        _flippy_add_head_elements($node);
      }
    }

    return $build;
  }

  /**
   * Implements \Drupal\block\BlockBase::access().
   */
  public function access(AccountInterface $account) {
    return $account->hasPermission('access content');
  }
}

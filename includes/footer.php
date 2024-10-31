<?php
if ( preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF']) ) {
     die('You are not allowed to call this page directly.');
}
/**
 * footer.php - View for the footer of all special pages.
 *
 * @package RecipePress
 * @subpackage includes
 * @author GrandSlambert
 * @copyright 2009-2011
 * @access public
 * @since 1.0
 */
?>

<div style="clear:both;">
     <div class="postbox" style="width:49%; float:left">
          <h3 class="handl" style="margin:0; padding:3px;cursor:default;"><?php _e('Credits', 'recipe-press'); ?></h3>
          <div style="padding:8px;">
               <p>
                    <?php
                    printf(__('Thank you for trying the %1$s plugin - I hope you find it useful. For the latest updates on this plugin, vist the %2$s.', 'recipe-press'),
                            $this->pluginName,
                            '<a href="http://recipepress.net" target="_blank">' . __('official site', 'recipe-press') . '</a>'
                    ); ?>

                    <?php
                    printf(__('If you have questions about this plugin, please use our %1$s and take a look at our %2$s.', 'recipe-press'),
                            '<a href="http://support.recipepress.net/" target="_blank">' . __('Support Forum', 'recipe-press') . '</a>',
                            '<a href="http://wiki.recipepress.net/" target="_blank">' . __('Documentation Project', 'recipe-press') . '</a>'
                    );
                    ?>

                    <?php
                    printf(__('Please report bugs on the %1$s page.', 'recipe-press'),
                            '<a href="http://code.google.com/p/recipe-press/issues/list" target="_blank">' . __('Bug Tracking', 'recipe-press') . '</a>'
                    ); ?>
               </p>
               <p>
<?php
                    printf(__('This plugin is &copy; %1$s by %2$s and is released under the %3$s', 'recipe-press'),
                            '2009-' . date("Y"),
                            '<a href="http://grandslambert.com" target="_blank">GrandSlambert, Inc.</a>',
                            '<a href="http://www.gnu.org/licenses/gpl.html" target="_blank">' . __('GNU General Public License', 'recipe-press') . '</a>'
                    ); ?>
               </p>
          </div>
     </div>
     <div class="postbox" style="width:49%; float:right">
          <h3 class="handl" style="margin:0; padding:3px;cursor:default;"><?php _e('Donate', 'recipe-press'); ?></h3>
          <div style="padding:8px">
               <p>
<?php printf(__('If you find this plugin useful, please consider supporting this and our other great %1$s.', 'recipe-press'), '<a href="http://wordpress.grandslambert.com/plugins.html" target="_blank">' . __('plugins', 'recipe-press') . '</a>'); ?>
                    <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&amp;hosted_button_id=8972425" target="_blank"><?php _e('Donate a few bucks!', 'recipe-press'); ?></a>
               </p>
               <p style="text-align: center;"><a target="_blank" href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&amp;hosted_button_id=8972425"><img width="122" height="47" alt="paypal_btn_donateCC_LG" src="http://grandslambert.com/files/2010/06/btn_donateCC_LG.gif" title="paypal_btn_donateCC_LG" class="aligncenter size-full wp-image-174"/></a></p>
          </div>
     </div>
</div>
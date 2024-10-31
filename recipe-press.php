<?php

/*
  Plugin Name: RecipePress
  Plugin URI: http://recipepress.net
  Description: Turn your Wordpress site into a full fledged recipe sharing system. Allow users to submit recipes, organize recipes in hierarchal categories, make comments, and embed recipes in posts and pages.
  Version: 2.2
  Author: grandslambert
  Author URI: http://grandslambert.com/
  License: GPL2

 * *************************************************************************

  Copyright (C) 2009-2011 GrandSlambert

  This program is free software: you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program.  If not, see http://www.gnu.org/licenses/

 * *************************************************************************

 */

require_once('classes/recipe-press-core.php');
require_once('classes/administration.php');
require_once('classes/shortcodes.php');
require_once('classes/initialize.php');
include_once('includes/form_tags.php');
include_once('includes/template_tags.php');
include_once('includes/taxonomy_tags.php');
include_once('includes/recipe_box_tags.php');
require_once('includes/inflector.php');

if ( !function_exists('recaptcha_get_html') ) {
     require_once('includes/recaptchalib.php');
}

class recipePress extends recipePressCore {

     /**
      * Initialize the plugin.
      */
     function recipePress() {
          parent::recipePressCore();

          /* Add Options Pages and Links */
          add_action('wp_loaded', array(&$this, 'wp_loaded'));
          add_action('wp_print_styles', array(&$this, 'wp_print_styles'));
          add_action('wp_print_scripts', array(&$this, 'wp_print_scripts'));
          add_filter('query_vars', array(&$this, 'query_vars'));
          add_action('template_redirect', array(&$this, 'template_redirect'));
          add_action('parse_request', array(&$this, 'catch_recipe_form'));
          add_action('pre_get_posts', array(&$this, 'pre_get_posts'));
          add_action('wp_ajax_ingredient_lookup', array(&$this, 'ingredient_lookup'));
          add_action('wp_ajax_nopriv_ingredient_lookup', array(&$this, 'ingredient_lookup'));
          add_action('wp_ajax_recipe_press_view_all_tax', array(&$this, 'view_all_taxonomy'));
          add_action('wp_ajax_nopriv_recipe_press_view_all_tax', array(&$this, 'view_all_taxonomy'));

          /* Optional filters */
          if ( $this->options['add-to-author-list'] ) {
               add_filter('request', array(&$this, 'add_recipe_to_author_list'));
          }

          /* Content filtering */
          if ( !$this->options['disable-content-filter'] ) {
               add_filter('the_content', array(&$this, 'the_content_filter'));
               add_filter('the_excerpt', array(&$this, 'the_content_filter'));
          }

          /* Add Custom Theme Directory */
          if ( function_exists('register_theme_directory') ) {
               register_theme_directory($this->pluginPath . 'themes');
          }

          if ( is_admin ( ) ) {
               RecipePressAdmin::initialize();
               if ( $this->options['use-recipe-box'] ) {
                    include_once($this->pluginPath . 'classes/recipe-box.php');
                    recipe_press_recipe_box::initialize();
               }
          } else {
               RecipePressShortCodes::initialize();
          }

          recipePress_Init::initialize();
     }

     /**
      * Add additional query vars for special features.
      *
      * @param string $qvars
      * @return string
      */
     function query_vars($qvars) {
          $qvars[] = 'print';
          $qvars[] = 'recipe-form';
          $qvars[] = 'recipe-taxonomy';
          $qvars[] = 'recipe-box';
          $qvars[] = 'box-page';
          return $qvars;
     }

     /**
      * Checks if the Recipe Form was submitted and creates the recipe.
      */
     function catch_recipe_form() {
          /* Check if form is submitted */
          if ( isset($_POST['recipe-form-nonce']) and wp_verify_nonce($_POST['recipe-form-nonce'], 'recipe-form-submit') ) {
               $errors = $this->create_recipe();

               if ( count($errors) == 0 ) {
                    $page = get_page($this->options['form-redirect']);

                    if ( $page->ID == $post->ID ) {
                         $url = get_option('home');
                    } else {
                         $url = get_post_permalink($page->ID, true);
                    }

                    wp_redirect($url);
                    exit();
               }
          } elseif ( isset($_POST['recipe-form-nonce']) ) {
               wp_die(__('This form was submitted without a proper nonce. Please contact the site administrator.', 'recipe-press'));
          }
     }

     /**
      * Catch the submit form and other special requests.
      *
      * @global  $wp_query
      * @global <type> $post
      * @return <type>
      */
     function template_redirect() {
          global $wp_query, $post;


          if ( !is_object($post) ) {
               return;
          }

          if ( $post->post_type == 'recipe' and get_query_var('print') ) {
               remove_filter('the_content', array(&$this, 'the_content_filter'));
               remove_filter('the_excerpt', array(&$this, 'the_content_filter'));
               include ($this->get_template('recipe-print'));
               exit;
          }
     }

     /**
      * Overrides the post count for recipes.
      *
      * @global <object> $wp_query
      * @return <boolean>
      */
     function pre_get_posts() {
          global $wp_query;

          if ( !isset($wp_query->query_vars['post_type']) or (isset($wp_query->query_vars['post_type']) and $wp_query->query_vars['post_type'] != 'recipe' or is_admin()) ) {
               return;
          }

          $wp_query->set('orderby', $this->options['recipe-orderby']);
          $wp_query->set('order', $this->options['recipe-order']);

          if ( $this->options['recipe-count'] != 'default' ) {
               $wp_query->set('posts_per_page', $this->options['recipe-count']);
          }
     }

     /**
      * Set up the styles and scripts for the plugin.
      */
     function wp_loaded() {
          wp_register_style('recipePressIncludedCSS', $this->pluginURL . 'templates/recipe-press.css');
          wp_register_style('recipePressCSS', $this->get_template('recipe-press', '.css', 'url'));
          wp_register_script('recipe-press-js', $this->pluginURL . 'js/recipe-press.js');
          wp_register_script('recipe-press-form-js', $this->pluginURL . 'js/recipe-press-form.js');
     }

     /**
      * Print the stylesheets for the plugin.
      */
     function wp_print_styles() {

          if ( $this->options['custom-css'] ) {
               wp_enqueue_style('recipePressIncludedCSS');
               wp_enqueue_style('recipePressCSS');
          }
     }

     /**
      * Print the javascript needed for the form.
      */
     function wp_print_scripts() {
          wp_localize_script('recipe-press-js', 'RPAJAX', array(
               'ajaxurl' => admin_url('admin-ajax.php'),
               'remove_from_box' => sprintf(__('Are you sure you want to remove this recipe from %1$s?', 'recipe-press'), $this->options['recipe-box-title'])
                  )
          );
          wp_enqueue_script('jquery');
          wp_enqueue_script('jquery-ui-sortable');
          wp_enqueue_script('suggest');
          wp_enqueue_script('recipe-press-js');
          wp_enqueue_script('recipe-press-form-js');
     }

     function check_form() {
          $isErrors = false;

          $this->recipe = $this->input();

          if ( is_array($this->options['required-fields']) ) {
               foreach ( $this->options['required-fields'] as $field ) {
                    if ( !$this->recipe[$field] and $field != 'image' ) {
                         $this->errors[$field] = $this->formFieldNames[$field];
                         $isErrors = true;
                    }
               }
          }

          /* Check Image */
          if ( isset($_POST['submit']) && is_uploaded_file($_FILES['image']['tmp_name']) ) {
               $photo_file = $_FILES["image"]["name"];
               $allowedExtensions = array("jpg", "jpeg", "gif", "png");

               if ( !in_array(end(explode(".", strtolower($photo_file))), $allowedExtensions) ) {
                    $this->errors['image'] = sprintf(__('Error: File Upload Failed: Invalid File Extension. (%1$s)', 'recipe-press'), $photo_file);
                    $isErrors = true;
               }

               if ( $_FILES["image"]["error"] == 2 ) {
                    $this->errors['image'] = __('Error: Image file too large', 'recipe-press');
                    $isErrors = true;
               }

               if ( $_FILES["image"]["size"] > 2097152 ) {
                    $this->errors = __('Error: Image file too large', 'recipe-press');
                    $isErrors = true;
               }
          } elseif ( isset($this->options['required-fields']['image']) and $this->options['required-fields']['image'] ) {
               $this->errors['image'] = __('Recipe image is required.', 'recipe-press');
               $isErrors = true;
          }

          /* Check the reCaptcha */
          if ( $this->options['recaptcha-public'] and $this->showCaptcha ) {
               if ( $_POST['check_captcha'] ) {
                    $this->errors['validate'] = $this->check_recaptcha();
               } else {
                    $this->showCaptcha = false;
               }

               if ( $this->errors['validate']->is_valid ) {
                    $this->showCaptcha = false;
               } else {
                    $isErrors = true;
               }
          }

          return $isErrors;
     }

     function check_recaptcha() {
          return recaptcha_check_answer(
                  $this->options['recaptcha-private'],
                  $_SERVER["REMOTE_ADDR"],
                  $_POST["recaptcha_challenge_field"],
                  $_POST["recaptcha_response_field"]
          );
     }

     /**
      * Create new recipe from the front-end form.
      *
      * @return integer
      */
     function create_recipe() {
          if ( $this->recipeCreated ) {
               return;
          }

          $errors = $this->check_form();

          if ( $errors ) {
               return $this->errors;
          } else {
               do_action('rp_before_save');

               $this->errors = array();

               $post = array(
                    'post_title' => $this->recipe['title'],
                    'comment_status' => 'open',
                    'ping_status' => 'open',
                    'post_author' => $this->recipe['user_id'],
                    'post_content' => $this->recipe['instructions'],
                    'post_date' => date('Y-m-d H:i:s'),
                    'post_date_gmt' => gmdate('Y-m-d H:i:s'),
                    'post_excerpt' => $this->recipe['notes'],
                    'post_status' => $this->recipe['status'],
                    'post_type' => 'recipe',
               );

               remove_action('save_post', array(&$this, 'save_recipe'));

               if ( $post = wp_insert_post($post) ) {
                    $postData = get_post($post);
                    require_once ( ABSPATH . 'wp-admin/includes/image.php' );

                    $this->recipeCreated = true;

                    /* Handle image if uploaded */
                    if ( isset($_POST['submit']) && is_uploaded_file($_FILES['image']['tmp_name']) ) {
                         require_once(ABSPATH . 'wp-admin/includes/admin.php');
                         $id = media_handle_upload('image', $post);

                         if ( $id ) {
                              add_post_meta($post, '_thumbnail_id', $id, true);
                         }

                         unset($_FILES);
                    }

                    /* Handle taxonomies */
                    foreach ( $this->options['taxonomies'] as $taxonomy => $settings ) {
                         if ( isset($_POST[$taxonomy]) ) {
                              if ( isset($this->options['taxonomies'][$taxonomy]['hierarchical']) and $this->options['taxonomies'][$taxonomy]['hierarchical'] ) {
                                   $terms = array();
                                   foreach ( $_POST[$taxonomy] as $tax ) {
                                        $tax = get_term($tax, $taxonomy);
                                        if ( is_object($tax) ) {
                                             $terms[] = $tax->name;
                                        }
                                   }
                              } else {
                                   $terms = $_POST[$taxonomy];
                              }
                              $affected = wp_set_object_terms($post, $terms, $taxonomy);
                         }
                    }

                    /* Save custom fields */
                    add_post_meta($post, '_recipe_prep_time_value', $this->recipe['prep_time'], true);
                    add_post_meta($post, '_recipe_cook_time_value', $this->recipe['cook_time'], true);
                    add_post_meta($post, '_recipe_ready_time_value', $this->readyTime($this->recipe['prep_time'], $this->recipe['cook_time']), true);
                    add_post_meta($post, '_recipe_servings_value', $this->recipe['servings'], true);
                    add_post_meta($post, '_recipe_servings_value', $this->recipe['servings'], true);
                    add_post_meta($post, '_recipe_serving_size_value', $this->recipe['serving_size'], true);

                    if ( !is_user_logged_in() ) {
                         add_post_meta($post, 'recipe_author', $_POST['submitter'], true);
                         add_post_meta($post, 'recipe_author_email', $_POST['submitter_email'], true);
                    }

                    /* Save ingredients */
                    if ( is_array($this->recipe['ingredients']) ) {
                         $ingredients = $this->recipe['ingredients'];
                    } else {
                         $ingredients = unserialize($this->recipe['ingredients']);
                    }

                    $this->save_ingredients($post, $ingredients);
               } else {
                    $this->errors['create'] = __('Error creating recipe.', 'recipe-press');
               }
          }

          add_action('save_post', array(&$this, 'save_recipe'));
          return $this->errors;
     }

     /**
      * Add recipes to author list
      */
     function add_recipe_to_author_list($query) {
          if ( isset($query['author_name']) ) {
               if ( isset($query['post_type']) && is_array($query['post_type']) ) {
                    array_push($query['post_type'], 'recipe');
               } else {
                    $query['post_type'] = array('post', 'recipe');
               }
          }
          return $query;
     }

     /**
      * AJAX handler for view all taxonomies
      */
     function view_all_taxonomy() {
          global $this_instance;
          $instance = get_option('widget_recipe_press_taxonomy_widget');

          $defaults = array(
               'orderby' => $this->options['widget-orderby'],
               'order' => $this->options['widget-order'],
               'style' => $this->options['widget-style'],
               'thumbnail_size' => 'recipe-press-thumb',
               'hide-empty' => $this->options['widget-hide-empty'],
               'exclude' => NULL,
               'include' => NULL,
               'taxonomy' => 'recipe-category',
               'title' => '',
               'items' => $this->options['widget-items'],
               'show-count' => false,
               'before-count' => ' ( ',
               'after-count' => ' ) ',
               'show-view-all' => false,
               'view-all-text' => '&darr;' . __('View All', 'recipe-press'),
               'submit_link' => false,
               'list-class' => 'recipe-press-taxonomy-widget',
               'item-class' => 'recipe-press-taxonomy-item',
               'child-class' => 'recipe-press-child-item',
               'target' => 'none',
          );

          $this_instance = $instance = wp_parse_args($instance['5'], $defaults);

          $taxArgs = array(
               'orderby' => $instance['orderby'],
               'order' => $instance['order'],
               'style' => $instance['style'],
               'show_count' => $instance['show-count'],
               'hide_empty' => $instance['hide-empty'],
               'use_desc_for_title' => 1,
               'child_of' => 0,
               'exclude' => $instance['exclude'],
               'include' => get_published_categories($_REQUEST['tax']),
               'hierarchical' => ($instance['taxonomy'] == 'recipe-ingredient') ? false : $this->options['taxonomies'][$instance['taxonomy']]['hierarchical'],
               'title_li' => '',
               'show_option_none' => __('No categories'),
               'number' => NULL,
               'echo' => 1,
               'depth' => 0,
               'current_category' => 0,
               'pad_counts' => false,
               'taxonomy' => $_REQUEST['tax'],
               'walker' => new Walker_RP_Taxonomy
          );

          wp_list_categories($taxArgs);
          echo '<div class="cleared" style="clear:both"></div>';

          die();
     }

     /**
      * AJAX Handler for the ingredient lookup form.
      */
     function ingredient_lookup() {

          $args = array(
               'name__like' => $_REQUEST['q'],
               'number' => 20,
               'ordeby' => 'name',
               'order' => 'asc'
          );

          $terms = get_terms('recipe-ingredient', $args);

          foreach ( $terms as $term ) {
               echo $term->name . '<span class="ingredient-id"> : ' . $term->term_id . "</span>\n";
          }

          die();
     }

}

/* Instantiate the Plugin */
$RECIPEPRESSOBJ = new recipePress;

/* Add Widgets */
include_once($RECIPEPRESSOBJ->pluginPath . 'widgets/list-widget.php');
include_once($RECIPEPRESSOBJ->pluginPath . 'widgets/category-widget.php');
include_once($RECIPEPRESSOBJ->pluginPath . 'widgets/taxonomy-widget.php');

/* Activation Hook */
register_activation_hook(__FILE__, 'recipe_press_activation');

function recipe_press_activation() {
     global $wpdb;
     if ( !post_type_exists('recipes') ) {
          $wpdb->update($wpdb->prefix . 'posts', array('post_type' => 'recipe'), array('post_type' => 'recipes'));
     }

     /* Rename the built in taxonomies to be singular names */
     $wpdb->update($wpdb->prefix . 'term_taxonomy', array('taxonomy' => 'recipe-category'), array('taxonomy' => 'recipe-categories'));
     $wpdb->update($wpdb->prefix . 'term_taxonomy', array('taxonomy' => 'recipe-cuisine'), array('taxonomy' => 'recipe-cuisines'));
}
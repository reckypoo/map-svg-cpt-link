<?php
/*
Plugin Name: Map SVG CPT Link
description: This plugin connects a custom post type that you have created and updates or creates a MapSVG item for each custom post. Database must be MySQL.
Version: 1.5.0
Author: Jesse Corkill
*/
//Globals
$cpt_data;
//End Globals

//ACF SETUP START
if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/** Start: Detect ACF Pro plugin. Include if not present. */
if ( !class_exists('acf') ) { // if ACF Pro plugin does not currently exist
  /** Start: Customize ACF path */
  add_filter('acf/settings/path', 'cysp_acf_settings_path');
  function cysp_acf_settings_path( $path ) {
    $path = plugin_dir_path( __FILE__ ) . 'inc/acf-incs/acf/';
    return $path;
  }
  /** End: Customize ACF path */
  /** Start: Customize ACF dir */
  add_filter('acf/settings/dir', 'cysp_acf_settings_dir');
  function cysp_acf_settings_dir( $path ) {
    $dir = plugin_dir_url( __FILE__ ) . 'inc/acf-incs/acf/';
    return $dir;
  }
  /** End: Customize ACF path */
  /** Start: Hide ACF field group menu item */
  add_filter('acf/settings/show_admin', '__return_false');
  /** End: Hide ACF field group menu item */
  /** Start: Include ACF */
  include_once( plugin_dir_path( __FILE__ ) . 'inc/acf-incs/acf/acf.php' );
  /** End: Include ACF */
  /** Start: Create JSON save point */
  add_filter('acf/settings/save_json', 'cysp_acf_json_save_point');
  function cysp_acf_json_save_point( $path ) {
    $path = plugin_dir_path( __FILE__ ) . 'inc/acf-incs/acf-json/';
    return $path;
  }
  /** End: Create JSON save point */
  /** Start: Create JSON load point */
  add_filter('acf/settings/load_json', 'cysp_acf_json_load_point');
  /** End: Create JSON load point */
  /** Start: Stop ACF upgrade notifications */
  add_filter( 'site_transient_update_plugins', 'cysp_stop_acf_update_notifications', 11 );
  function cysp_stop_acf_update_notifications( $value ) {
    unset( $value->response[ plugin_dir_path( __FILE__ ) . 'inc/acf-incs/acf/acf.php'] );
    return $value;
  }
  /** End: Stop ACF upgrade notifications */
} else { // else ACF Pro plugin does exist
  /** Start: Create JSON load point */
  add_filter('acf/settings/load_json', 'cysp_acf_json_load_point');
  /** End: Create JSON load point */
} // end-if ACF Pro plugin does not currently exist
/** End: Detect ACF Pro plugin. Include if not present. */
/** Start: Function to create JSON load point */
function cysp_acf_json_load_point( $paths ) {
  $paths[] = plugin_dir_path( __FILE__ ) . 'inc/acf-incs/acf-json-load';
  return $paths;
}
/** End: Function to create JSON load point */
//ACF SETUP END


class MSCL{
  public static function init(){
    self::load('__loader');
  }
  private static function load($file){
    require __DIR__ . '/inc/' . $file . '.php';
  }
}
MSCL::init();

global $wpdb;
$table_name = $wpdb->prefix . 'mapsvg_database_' . get_field('map_id', 'options');

//echo esc_html(show_array(wp_get_post_categories('83')));

//Create Index Array of Available Categories
$cat_name = '';

//Remove Unwanted Cats from Categories
$unwanted_ids = array();
$villa_id = get_terms(['name' => 'Villa'])->id;
$veranda_id = get_terms(['name' => 'Veranda'])->id;
array_push($unwanted_ids, $villa_id);
array_push($unwanted_ids, $veranda_id);

$cat_args = array(
  'taxonomy' => 'category',
  'hide_empty' => true,
  'name' => $cat_name,
  'exclude' => $unwanted_ids //exclude villas and verandas
);
$cats = get_terms($cat_args);
//console_log($cats);
$cat_names = array();
foreach($cats as $cat) {
  $cat_names[] = $cat->name;
}
//console_log($cat_indx);
if($cpt_data){
  //console_log(count($cpt_data));
  foreach($cpt_data as $post){
    $term = "";
	  //Get Categories of Post & Parse
	  $post_cats = get_the_category($post->ID);
	  foreach($post_cats as $post_cat){
		  if($post_cat->name != "Veranda" && $post_cat->name != "Villa"){
        //Get Post's main taxonomy (category)
        $term = get_term($post_cat->term_id);
        //console_log($the_term);
		  }
	  }
    $pin_array = [
      'Available Now' => 'pin1_green.png',
      'Currently Building' => 'pin1_purple.png',
      'Future Build' => 'pin1_grey.png',
      'Sold' => 'pin1_red.png',
      'Model' => 'pin1_blue.png',
      'Contracted' => 'pin1_orange.png',
    ];
    $availability = get_field('status', $post->ID);

    $featured_image = wp_get_attachment_image_src(get_field('featured_image','category_' . $term->term_id),'full');
    $db_fields = array(
      //Post Data
      'title' => $post->post_title,
      'post_id' => $post->ID,
      'location_address' => get_field('address', $post->ID),
      'category_text' => $term->name,
      'category' => array_search($term->name, $cat_names) + 1,
      'availability' => $availability,
      'location_img' => $pin_array[$availability],
      //Tax Data
      'description' => term_description($term->term_id),
      'link' => '/' . strtolower($term->name),
      'featured_image' => $featured_image[0],
      'price' => get_field('price', 'category_' . $term->term_id),
    );
    // KEY: %d interger (whole numbers only) %s string %f float
    $db_format = array(
      '%s',
      '%d',
      '%s',
      '%s',
      '%s',
      '%s',
      '%s',
      '%s',
      '%s',
      '%s',
    );
    $db_where = array(
      'post_id' => $post->ID
    );
    //console_log($post->ID);


    // //Add new identifier column to DB table.
    // $col_query_postid = $wpdb->get_row("SELECT * FROM " . $table_name . " WHERE post_id IS NOT NULL");
    // //Add column if not present.
    // if(!isset($col_query_postid->post_id)){
    //   $wpdb->query("ALTER TABLE " . $table_name . " ADD post_id INT(11)");
    //   //console_log('Table altered!');
    // }
    // //New Price Column
    // $col_query_price = $wpdb->get_row("SELECT * FROM " . $table_name . " WHERE price IS NOT NULL");
    // //Add column if not present.
    // if(!isset($col_query_price->price)){
    //   $wpdb->query("ALTER TABLE " . $table_name . " ADD price VARCHAR(255)");
    //   //console_log('Table altered!');
    // }
    // //New Status Column
    // $col_query_status = $wpdb->get_row("SELECT * FROM " . $table_name . " WHERE availability IS NOT NULL");
    // //Add column if not present.
    // if(!isset($col_query_status->availability)){
    //   $wpdb->query("ALTER TABLE " . $table_name . " ADD availability VARCHAR(255)");
    //   //console_log('Table altered!');
    // }


    //Dynamic Column Checker
    foreach(array_keys($db_fields) as $column){
      //Check for existing Column
      $col_query = $wpdb->get_col("SELECT * FROM " . $table_name . " WHERE ". $column);
      //Add column if not present.
      if(!isset($col_query)){
        $wpdb->query("ALTER TABLE " . $table_name . " ADD " . $column . " VARCHAR(255)");
        //console_log('Table altered! Column ' . $column);
      }
    }




    //Check if post already exists in table..
    $row = $wpdb->get_row("SELECT * FROM " . $table_name . " WHERE post_id = " . "'". $post->ID . "'", OBJECT);
    //Post does NOT exist, so add a new row.
    if(!$row){
      $wpdb->insert($table_name, $db_fields,  $db_format);
    }
    //Post does exist in table, so update row's values
    else{
      array_push($db_format, '%s');

      $wpdb->update($table_name, $db_fields, $db_where, $db_format);
    }
    $wpdb->show_errors();
  }
}
?>

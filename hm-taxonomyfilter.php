<?php
/*
Plugin Name: HM Taxonomy Filter
Version: 0.1
Description: Filter posts by multiple custom taxonomies and terms.
Plugin URI: http://hatsumatsu.de/
Author: HATSUMATSU, Martin Wecke
Author URI: http://hatsumatsu.de/
*/

/**
 * I11n
 */

load_plugin_textdomain( 'hm-taxonomyfilter', '/wp-content/plugins/hm-taxonomyfilter/' );


/**
 * Register a custom query var 
 */

function hm_taxonomyfilter_custom_query_vars( $query_vars ) {
  
  $query_vars[] = 'hm-taxonomyfilter';
  return $query_vars;

}

add_filter( 'query_vars', 'hm_taxonomyfilter_custom_query_vars' );


/**
 * Register custom rewrite rules to translate the /filter/{filter-string} URL structure 
 * to our custom query var  
 */

function hm_taxonomyfilter_custom_rewrite_rules() {

  // filter
  add_rewrite_rule(
    'filter/([^/]+)/?$',
    'index.php?&hm-taxonomyfilter=$matches[1]',
    'top'
    );

  // filter + paged
  add_rewrite_rule(
    'filter/([^/]+)/page/([0-9]+)/?$',
    'index.php?&hm-taxonomyfilter=$matches[1]&paged=$matches[2]',
    'top'
    );

}

add_action( 'init', 'hm_taxonomyfilter_custom_rewrite_rules' );


/**
 * Translate our custom query var to the tax_query of Wordpress core 
 */

function hm_taxonomyfilter_modify_query( $query ) {

  if( isset( $query->query_vars[ 'hm-taxonomyfilter' ] ) ) {
    
    $tax_query = array();
    $tax_query['relation'] = 'AND';

    foreach( explode( ',', $query->query_vars[ 'hm-taxonomyfilter' ] ) as $filter_query ) {

      $filter = explode( ':', $filter_query );
      $filter_tax   = $filter[0];
      $filter_terms = explode( '+', $filter[1] );

      $tax_query[] = array(
        'taxonomy' => $filter_tax,
        'field'    => 'slug',
        'terms'    => $filter_terms,
        'operator' => 'AND'
      );

    }

    $query->query_vars[ 'tax_query' ] = $tax_query;

  }

  return $query;

}

if( !is_admin() ) { 
  add_filter( 'pre_get_posts', 'hm_taxonomyfilter_modify_query' );
}


/**
 * Gets the value of our custom query var and returns it as associative array
 *
 * @return array
 */

function hm_taxonomyfilter_get_filter() {

    $query = get_query_var( 'hm-taxonomyfilter' );

    $filter = array();
    $rules = explode( ',', $query );

    foreach( $rules as $r ) {

      $r = explode( ':', $r );
      $filter[ $r[0] ] = explode( '+', $r[1] );

    }

    $filter = array_filter_recursive( $filter );

    return $filter;

}


/**
 * Modifies the filter array created by hm_taxonomyfilter_get_filter()
 *
 * @param array   $filter     filter array
 * @param string  $mode       'add' or 'remove'
 * @param string  $taxonomy   taxonomy 'name' (slug)
 * @param string  $term       term slug 
 *
 * @return array
 */

function hm_taxonomyfilter_modify_filter( $filter, $mode, $taxonomy, $term ) {

  $_f = $filter;
  $__f = $filter[$taxonomy];

  if( $mode == 'remove' ) {

    $__f = array_diff( $__f, array( $term ) );

  }

  if( $mode == 'add' ) {

    $__f[] = $term;

  }

  $_f[$taxonomy] = $__f;
  $filter = $_f;

  $filter = array_filter( $filter );

  return $filter;

}


/**
 * Translates the filter array to a string used as filter slug
 *
 * @param array   $filter     filter array
 *
 * @return string   'filter/{taxonomy-a}:{term-a1}+{term-a2},{taxonomy-b}:{term-b3}'
 */

function hm_taxonomyfilter_get_filter_link( $filter ) {

  $link = '';

  foreach( $filter as $_f => $__f ) {
    $link .= $_f . ':' . implode( '+', $__f ) . ',';
  }

  $link = substr( $link, 0, ( strlen( $link ) - 1 ) );
  
  if( $link != '' ) {
    $link = 'filter/' . $link;
  }

  return $link;

}


/**
 * Template tag:
 * Renders an hierachical list of taxonomies and their term links.
 * Clicking on a terms adds it to the current filter.
 * Clicking on terms already included in the filter removes them from the current filter.
 */

function hm_taxonomyfilter_navigation() {

  global $wp_query;

  $base_url = hm_taxonomyfilter_get_base_url();

  $taxonomies = get_taxonomies( array(
    'public'   => true,
    '_builtin' => false,
    ), 'object' );

  if( $taxonomies ) {

    $filter = hm_taxonomyfilter_get_filter();

    echo '<ul>';

    foreach( $taxonomies as $taxonomy ) {

      echo '<li>';
      echo '<h4>' . $taxonomy->labels->singular_name . '</h4>';

      $terms = get_terms( $taxonomy->name, array() );

      if( $terms ) {

        echo '<ul>';

        foreach( $terms as $term ) {

          $is_current = ( @in_array( $term->slug, $filter[$taxonomy->name] ) ) ? true : false;

          if( $is_current ) {

            // remove term from filter
            $filter_term = hm_taxonomyfilter_modify_filter( $filter, 'remove', $taxonomy->name, $term->slug );

          } else {

            // add term to filter
            $filter_term = hm_taxonomyfilter_modify_filter( $filter, 'add', $taxonomy->name, $term->slug );

          }

          $link = hm_taxonomyfilter_get_filter_link( $filter_term );

          $current_class = ( $is_current ) ? 'current' : '';

          echo '<li class="' . $current_class . '" data-term="' . $term->slug . '">';
          echo '<a href="http://' . $base_url . $link . '">';
          echo $term->name;
          echo '</a>';
          echo '</li>';

        }

        echo '</ul>';

      }

      echo '</li>';

    }

    echo '</ul>';

  }

}


/**
 * Template tag:
 * Renders an hierachical list of the taxonomies 
 * and their term currently used by the filter.
 * Clicking on a term removes it from the current filter.
 */

function hm_taxonomyfilter_status() {

  $base_url = hm_taxonomyfilter_get_base_url();

  $filter = hm_taxonomyfilter_get_filter();

  if( $filter ) {

    echo '<ul>';

    foreach( $filter as $taxonomy => $terms ) {

      $taxonomy = get_taxonomy( $taxonomy );

      echo '<li>';
      echo '<h4>';
      echo $taxonomy->labels->singular_name;
      echo '</h4>';
      echo '<ul>';

      foreach( $terms as $term => $t ) {

        $term = get_term_by( 'slug', $t, $taxonomy->name );

        $link = hm_taxonomyfilter_get_filter_link( hm_taxonomyfilter_modify_filter( $filter, 'remove', $taxonomy->name, $term->slug ) ); 

        echo '<li>';
        echo '<a href="http://' . $base_url . $link . '" title="">';
        echo $term->name;
        echo '</a>';
        echo '</li>';

      }

      echo '</ul>';
      echo '</li>';

    }

    echo '</ul>';

  }

}


/**
 * Helper functions
 */

/**
 * filter an array recursively
 * http://stackoverflow.com/questions/17923356/how-to-remove-empty-associative-array-entries
 */

function array_filter_recursive( $array ) {
 
  return array_filter( $array, function( $value ){return array_filter( $value ) != array(); } );

}


/**
 * Get the current archive page's permalink without any filter
 *
 * @return  $url  http//example.com/archive/
 */

function hm_taxonomyfilter_get_base_url() {

  $url = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
  $url = explode( 'filter/', $url );
  $url = $url[0];  

  return $url;

}
<?php 
/**
 * Plugin Name: Hides product variations without stock 
 * Plugin URI:	
 * Description:	Corrige o problema de produtos variantes Woocommerce não serem removidos da lista filtrada por atributos quando fora de estoque.
 * Version:		1.0
 * Author:		Felipe Peixoto
 * Author URI:	http://felipepeixoto.tecnologia.ws/
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}
function HPVWS_remove_if_out_of_stock($query_vars){
	
	$query_vars['paged'] = 1;
	$query_vars['posts_per_page'] = 999;
		
	$wp_query = new WP_Query($query_vars);

 	$remove_ids = array(); 
 	$filtros = array();
	foreach ($_GET as $key => $v) {
		$v = sanitize_text_field( trim($v));
		$key = sanitize_text_field( trim($key));
		if (strpos($key, 'filter_') !== FALSE) {
			$filtros[str_replace('filter_', '', $key)] = $v;
		}
	}  
    foreach ($wp_query->posts as $key => $post) {
    	$produto =  wc_get_product( $post->ID );
    	$remove = true;
		foreach ($filtros as $F_key => $F_value) {
	        $slugmap = array();
	        $attribs = $produto->get_variation_attributes();
	        $terms = get_terms( sanitize_title( 'pa_'.$F_key ));
	        if (stripos($F_value, ',') !== FALSE ) {
	        	$F_value = explode(',', $F_value);
	        } else{ 
	        	$F_value = array($F_value);
	        }
	        if($terms){
	        	foreach($terms as $term){
	        		$slugmap[$term->slug]=$term->term_id;
	        		$available = $produto->get_available_variations();
			        if($available){
			        	foreach($available as $instockitem){
			        		
				            if(isset($instockitem["attributes"]["attribute_pa_".$F_key])){
				            	foreach ($F_value as $par_value) {	
				            		if($instockitem["attributes"]["attribute_pa_".$F_key] == $par_value && $instockitem["max_qty"]>0){
										$remove = false;
									}
				            	}
				            	
				            }
				        }
			    	}    
				}
	    	}
	    }
	    if ($remove) {
    		$remove_ids[] = $post->ID;
    	}
    }

    return $remove_ids;
}


if (isset($_GET)) {
	foreach ($_GET as $key => $value) {
		$_GET[$key] = sanitize_text_field( trim($value));
	}
	$keyGets = implode(',', array_keys($_GET));
	if (stripos($keyGets, 'filter_') !== FALSE ) {
		add_action( 'pre_get_posts', function ($wp_query) {
			if (!$wp_query->is_main_query() ) {
				return;
			}

			$remove_ids = HPVWS_remove_if_out_of_stock($wp_query->query_vars);


			$wp_query->set( 'post__not_in', $remove_ids );
		}, 999);
	}
}
function HPVWS_remove_if_out_of_stock_widgets($q){
	global $wpdb;

	$q['join']   = "
	INNER JOIN {$wpdb->posts} AS post_filho ON post_filho.post_parent = {$wpdb->posts}.ID
	INNER JOIN {$wpdb->postmeta} AS postmeta ON postmeta.post_id = post_filho.ID
	INNER JOIN {$wpdb->postmeta} AS postmeta2 ON postmeta2.post_id = post_filho.ID
	" . $q['join'];
	$q['where'] .= " 
	AND postmeta.meta_key = '_stock'
	AND postmeta2.meta_value = terms.slug
	AND postmeta.meta_value > 0 ";
	return $q;
}
add_filter('woocommerce_get_filtered_term_product_counts_query','HPVWS_remove_if_out_of_stock_widgets', 10, 1);

?>
<?php
/*
Plugin Name: RBGE Digital Repository Embedding
Description: Methods for embedding data from the RBGE digital repository
Version: 0.1
Author: Roger Hyam
License: GPL2
*/

/* Map Embedding */
function rbge_repo_embed_item( $atts ){
    
	$post_id = get_the_ID();
//	$longitude = get_post_meta($post_id, 'geo_longitude', true);
//	$latitude = get_post_meta($post_id, 'geo_latitude', true);

    // fetch the data from the repo
    
    // use a cached version of the data
    
    $uri = rbge_repo_get_uri_for_atts($atts);
    if(!$uri) return "[You must set an id, barcode or accession to embed from the repository]";
    $data = '';
    
    if(rbge_repo_embed_get_data($uri, $data)){
        
        // OK - we have data (from whereever) now let us render it!
        include_once(plugin_dir_path( __FILE__ ) . 'templates.php');
        return render_repo_item($data); //. '<pre>'. print_r($data, true) . '</pre>';
        
    }else{
        return "<strong>Error: $data</strong>";
    }
    

}
add_shortcode( 'rbge_repo', 'rbge_repo_embed_item' );

function rbge_repo_get_uri_for_atts($atts){
    
    $key = false;
    if(isset($atts['id'])){
        $key = $atts['id'];
    }elseif(isset($atts['barcode'])){
        $key = $atts['barcode'];
    }elseif(isset($atts['accession'])){
        $key = $atts['accession'];
    }else{
        return $key;
    }
    
    // return "http://repo.rbge.org.uk/service/item/php/$key";
    // fixme to real server
    return "https://repo.rbge.org.uk/service/item/php/catalogue_number/$key"; 
    
}

function rbge_repo_register_css(){
        
    global $posts;
    $pattern = get_shortcode_regex(); 
   
    preg_match_all('/'.$pattern.'/i', $posts[0]->post_content, $matches, PREG_PATTERN_ORDER); 
    
    if (is_array($matches)) {
        foreach($matches[2] as $m){
            if($m == 'rbge_repo'){
                wp_enqueue_style('rbge_repo_style', plugins_url('style.css', __FILE__));
            }
        }
    }
    
}
add_action('wp_enqueue_scripts', 'rbge_repo_register_css');


function rbge_repo_embed_save($post_id){
    
    error_log( "Got it going" );
    
    global $post;
    
    $pattern = get_shortcode_regex();
    error_log($pattern);

    // act if one of our short codes is in the post
    if ($post && preg_match_all( '/'. $pattern .'/s', $post->post_content, $matches )
        && array_key_exists( 2, $matches )
        && in_array( 'rbge_repo', $matches[2] ) ){
            
            foreach($matches[0] as $tag){
                
                if(preg_match('/^\[rbge_repo /i', $tag)){
                    $atts = shortcode_parse_atts($tag);
                    $uri = rbge_repo_get_uri_for_atts($atts);
                    if(!$uri) return;
                    
                    // schedule an event to update the data
                    wp_schedule_single_event(time(), 'exec_rbge_repo_embed_cache_data', array($uri, get_the_ID()));
                }
            }
            
    }
    
}
add_action( 'save_post', 'rbge_repo_embed_save' );

function rbge_repo_embed_get_data($uri, &$data){
    
    // try and get it out the cache.
    $data = get_post_meta(get_the_ID(), $uri, true);
    
    // fixme - remove for live;
    $data = false;
    
    if($data){
        
        // we stamp it with when we got it out the cache
        $data['wordpress_cached_retrieved_time'] = time();
        
        // if the data is older than a day set up a task to refresh it.
        if ($data['wordpress_cached_time'] + (24 * 60 * 60) < time()){
            $data['wordpress_cache_update_request_time'] = time();
            wp_schedule_single_event(time(), 'exec_rbge_repo_embed_cache_data', array($uri, get_the_ID()));
        }
        
        // we have data - even though it is old so return it
        // they can get new next time
        return true;
        
    }else{
        // no choice but to wait for data this time
        // will cause it to be cached
        $response =  rbge_repo_embed_cache_data($uri, get_the_ID());
        if($response){
            $data = $response;
            return true;
        }else{
            $data = "We had trouble retrieving the data for $uri";
            return false;
        }
        
    }
        
}

function rbge_repo_embed_cache_data($uri, $post_id){
    
    $ch = curl_init();    
    curl_setopt($ch, CURLOPT_URL, $uri); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
    $data = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
         
    if($http_status == 200){
        
        // make it into an array
        $data = unserialize($data);
        
        // stamp it with when we got it
        $data['wordpress_cached_time'] = time();
        
        // store it as metadata (this will re-serialise it)
        update_post_meta($post_id, $uri, $data); 
        
        // report success
        return $data;
        
    }else{
        return false;
    }
    
}
add_action('exec_rbge_repo_embed_cache_data', 'rbge_repo_embed_cache_data', 10, 2);



?>

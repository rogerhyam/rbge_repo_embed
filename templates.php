<?php

    function render_repo_item($data){
        
        // we only expect one so we just take the first
        $data = $data[0];
        
        $out = "";
        
        $out .= '<div class="rbge-repo-factsheet">';

        
        switch ($data->item_type) {
            case 'Herbarium Specimen':
                render_herbarium_specimen($out, $data);
                break;
                
            case 'Garden Accession':
                render_living_accession($out, $data);
                break;
            
            default:
                $out .= "<div>Unsupported repository item type: " . $data->item_type . "</div>";
                break;
        }
        
        $out .= '</div>';
        
        return $out;
    
    }
    
    
    function render_living_accession(&$out, $data){  
  
        $out .= '<div class="rbge-repo-label">RBGE Living Collections Accession Factsheet</div>';
        render_key_value_if_set($out, 'Accession Number:', $data, 'catalogue_number');
        render_taxonomy($out, $data);
        render_collector($out, $data);
        render_origin($out, $data);
        render_plants($out, $data);
 
        $out .= '<div class="rbge-repo-images">';
        render_item_images($out, $data);
        render_item_maps($out, $data);
        $out .= '</div>';
        
        //echo '<pre style="float:both;" >' . print_r($data, true) . "</pre>";
        $out .= '<div class="rbge-repo-linkout">';
        $out .= '<a href="http://data.rbge.org.uk/living/'. $data->catalogue_number .'">See full details in the Herbarium Catalogue &gt;&gt;</a>';
        $out .= '</div>';
        
        
    }
    
    function render_plants(&$out, $doc){
        
        // look down the list for plants
        if( $doc->item_type == 'Garden Plant' ){
            //echo '<pre style="float:both;" >' . print_r($doc, true) . "</pre>";
            $out .= '<div class="rbge-repo-detail">';
            render_key_value_if_set($out, 'Plant:', $doc, 'catalogue_number');
            render_key_value_if_set($out, 'Location:', $doc, 'storage_location_path');
            $out .= '</div>';
        }else{
            // recurse down the document tree and find the terminal plants
            if(isset($doc->derivatives)){
                foreach($doc->derivatives as $deriv){
                    render_plants($out, $deriv);
                }
            }
        }
        
    }
    
    function get_item_image_caption($item_image, $derived_from){
        
        if($item_image['object_creation_date']){
            $created = ' on ' . $item_image['object_creation_date'];
        }elseif($item_image['object_creation_year']){
            $created = ' in ' . $item_image['object_creation_year'];
        }
                    
        return $derived_from['title'] . ' photographed by ' . $item_image['creator'][0] . $created;
        
    }
    
    function render_herbarium_specimen(&$out, $data){
        
        $out .= '<div class="rbge-repo-label">RBGE '. $data->item_type . ' Factsheet</div>';
        render_key_value_if_set($out, 'Barcode:', $data, 'catalogue_number');
        render_taxonomy($out, $data);
        render_collector($out, $data);
        render_origin($out, $data);
        
        $out .= '<div class="rbge-repo-images">';
        render_item_images($out, $data);
        render_item_maps($out, $data);
        $out .= '</div>';
        
                
        $out .= '<div class="rbge-repo-linkout">';
        $out .= '<a href="http://data.rbge.org.uk/herb/'. $data->catalogue_number .'">See full details in the Herbarium Catalogue &gt;&gt;</a>';
        $out .= '</div>';
        
    }
    
     function render_collector(&$out, $data){
         $out .= '<div class="rbge-repo-detail">';
         $out .= render_key_value_if_set($out, 'Collector:', $data, 'creator');
         $out .= render_key_value_if_set($out, 'Year:', $data, 'object_created_year');
         $out .= '</div>';
     }
    
    function render_taxonomy(&$out, $data){
        $out .= render_key_value_if_set($out, 'Scientific Name:', $data, 'scientific_name_html');
        $out .= '<div class="rbge-repo-detail">';
        $out .= render_key_value_if_set($out, 'Family:', $data, 'family');
        $out .= render_key_value_if_set($out, 'Genus:', $data, 'genus');
        $out .= render_key_value_if_set($out, 'Epithet:', $data, 'epithet');
        $out .= '</div>';
    }
    
    function render_origin(&$out, $data){
        $out .= '<div class="rbge-repo-detail">';
        $out .= render_key_value_if_set($out, 'Origin:', $data, 'location');
        $out .= render_key_value_if_set($out, 'Country:', $data, 'country_name_official');
        
        // let's make elevation look nicer
        if(isset($data->elevation)){
            render_key_value($out, 'Elevation:', number_format($data->elevation) . "m");
        }
        
        $out .= '</div>';
    }
    
    function render_item_images(&$out, $doc){
        
        // if we have a image then render it
        if(
            isset($doc->mime_type_s)
            && $doc->mime_type_s == 'image/jpeg'
            && isset($doc->storage_location_path)
        ){
            // fixme 
            $base_uri = 'http://repo.rbge.org.uk/image_server.jpg?path='. urlencode($doc->storage_location_path) .'&kind=';
            $thumb_uri = $base_uri . '75-square';
            $large_uri = $base_uri . '1000';
            render_image($out, $thumb_uri, $large_uri, $doc->title[0]);
            
        }else{
            
            // recurse down the document tree and find the terminal jpegs
            if(isset($doc->derivatives)){
                foreach($doc->derivatives as $deriv){
                    render_item_images($out, $deriv);
                }
            }
            
        }
        
    }
    
    function render_item_maps(&$out, $doc){
        
       // echo "<pre>". print_r($doc, true)."</pre>";
        
        if(isset($doc->geolocation)){
            
            $lat_lon = $doc->geolocation;
            $api_key = 'AIzaSyAbbHcfYyNcQ7rHF-ikVWVtyOY4Kc6AQ1w'; // fixme should be config option
                
            if($data->item_type == 'Garden Plant'){
                $thumb_zoom = 14;
                $map_zoom = 17;
            }else{
                $thumb_zoom = 2;
                $map_zoom = 6;
            }
            
            $thumb_uri = "https://maps.googleapis.com/maps/api/staticmap?size=100x100&zoom=$thumb_zoom&center=$lat_lon&markers=size:tiny|color:red|$lat_lon&key=$api_key";
            
            $link_uri = "http://maps.google.com/maps?q=$lat_lon&ll=$lat_lon&z=$map_zoom";
            
            $caption = $data['title'] . " Location: $lat_lon";
            
            render_image($out, $thumb_uri, $link_uri, $caption, false);
            
        }else{
            if(isset($doc->derivatives)){
                foreach($doc->derivatives as $deriv){
                    render_item_maps($out, $deriv);
                }
            }
        }
        
    }
    
    
    function render_image(&$out, $thumb_uri, $large_uri, $caption, $lightbox = true){
        
        if($lightbox){
            $link_atts = 'class="cboxElement"  data-lightboxplus="lightbox['. get_the_ID() .']" ';
        }else{
            $link_atts = '';
        }

        $out .= '<div class="rbge-repo-image">';
        $out .= '<a  '. $link_atts .' title="' .$caption  . '" href="' . $large_uri . '" />';
        $out .= '<img alt="'. $caption .'" src="' . $thumb_uri . '" />';
        $out .= '</a>';
        $out .= '</div>';
        
    }
    
    
    function render_key_value_if_set(&$out, $key, $data, $name){
        if(isset($data->$name))render_key_value($out, $key, $data->$name);
    }
    
    function render_key_value(&$out, $key, $value){
        
        $out .= '<div class="rbge-repo-kv">';
        
        $out .= '<span class="rbge-repo-k">';
        $out .= $key;
        $out .= '</span>';        
        
        $out .= '<span class="rbge-repo-v">';
        
        if(is_array($value)) $render_val = implode(', ', $value);
        else $render_val = $value;
        
        $render_val = str_replace('<p>', '', $render_val);
        $render_val = str_replace('</p>', '', $render_val);
        $render_val = trim($render_val);
        
        $out .= $render_val;
        $out .= '</span>';        
        
        $out .= '</div>';
        
    }
    


?>

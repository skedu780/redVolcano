<?php
function get_page_contents($url) {
    // Assigning cURL options to an array
    $options = Array(
     
    CURLOPT_RETURNTRANSFER => TRUE,  // Setting cURL's option to return the webpage data
    CURLOPT_FOLLOWLOCATION => TRUE,  // Setting cURL to follow 'location' HTTP headers
    CURLOPT_AUTOREFERER => TRUE, // Automatically set the referer where following 'location' HTTP headers
    CURLOPT_HEADER=> TRUE,
    CURLOPT_CONNECTTIMEOUT => 1200,   // Setting the amount of time (in seconds) before the request times out
    CURLOPT_TIMEOUT => 1200,  // Setting the maximum amount of time for cURL to execute queries
    CURLOPT_MAXREDIRS => 10, // Setting the maximum number of redirections to follow
    CURLOPT_USERAGENT => "Googlebot/2.1 (+http://www.googlebot.com/bot.html)",  // Setting the useragent
    CURLOPT_URL => $url, // Setting cURL's URL option with the $url variable passed into the function
    CURLOPT_ENCODING=>'gzip,deflate',
     
    );
     
    $ch = curl_init();  // Initialising cURL
    curl_setopt_array($ch, $options);   // Setting cURL's options using the previously assigned array data in $options
     
    $data = curl_exec($ch); // Executing the cURL request and assigning the returned data to the $data variable
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);//to check whether any error occur or not
    if($httpcode!="200")
    {
        return "error";
    }
     
    return $data;   // Returning the data from the function
}

$all_reviews = [];

function search_in_page($html)
{
    global $all_reviews; //accessing global variable
    $dom = new DomDocument; // dom object
    $dom->loadHTML($html); // loading the page contents to dom object
    $content = $dom->getElementsByTagname('article'); // fetch all tags or article 
    
    $review = [];
    
    for ( $i = 0; $i < count($content); $i++ )
    {
        
        if($content[$i]->hasChildNodes()){
            $children = $content[$i]->childNodes;
              
            $children1 = $children->item(0)->childNodes->item(0)->childNodes->item(1)->childNodes;
            $review['name'] = $children1->item(0)->nodeValue;
            $review['total_reviews'] = $children1->item(1)->childNodes->item(0)->nodeValue;
            $review['country'] = $children1->item(1)->childNodes->item(1)->nodeValue;
            
            $children2 = $children->item(2)->childNodes;
            
            $review_details = [];
            if( $review['total_reviews'] == '1 review' ){ // for single review of user
                $comment_arr['rating'] = $children2->item(0)->getAttribute('data-service-review-rating');
                $comment_arr['review_date'] = $children2->item(0)->childNodes->item(1)->textContent;
                
                $comment_arr['comment_heading'] = $children2->item(1)->childNodes->item(0)->textContent;
                $comment_arr['comment_text'] = $children2->item(1)->childNodes->item(1)->textContent;
                
                $review_details[] = $comment_arr;
                
            } else { // for multiple review of user
                
                $children2 = $children->item(0)->childNodes->item(0)->childNodes->item(1)->getAttribute('href'); //
                
                $more_reviews = get_page_contents('https://uk.trustpilot.com'.$children2);
                
                $dom1 = new DomDocument;
                $dom1->loadHTML($more_reviews);
                $content1 = $dom1->getElementsByTagname('article');
                
                for ( $j = 0; $j < count($content1); $j++ )
                {
                    if($content1[$j]->hasChildNodes()){
                        $children_inner = $content1[$j]->childNodes->item(2)->childNodes;
                        
                        $comment_arr['rating'] = $children_inner->item(0)->getAttribute('data-service-review-rating'); // review rating is placed at attribute 
                        $comment_arr['review_date'] = $children_inner->item(0)->childNodes->item(1)->textContent;
                        
                        $comment_arr['comment_heading'] = $children_inner->item(1)->childNodes->item(0)->textContent;
                        $comment_arr['comment_text'] = $children_inner->item(1)->childNodes->item(1)->textContent;
                        
                        $comment_arr['reply'] = [];
                        if( $content1[$j]->childNodes->length == 4 ){
                            $comment_arr['reply']['from'] = $content1[$j]->childNodes->item(3)->childNodes->item(1)->childNodes->item(0)->childNodes->item(0)->textContent;
                            $comment_arr['reply']['date'] = $content1[$j]->childNodes->item(3)->childNodes->item(1)->childNodes->item(0)->childNodes->item(1)->textContent;
                            
                            $replyContent = $content1[$j]->childNodes->item(3)->childNodes->item(1)->childNodes->item(1)->childNodes->item(0); // to get the contents of p tag (reply tag)
                            $reply_txt = $replyContent->textContent;
                            //to get next lines reply text start
                            $sibling = $replyContent->nextSibling;
                            do {
                                $reply_txt .= "\r\n".$sibling->nodeValue;
                                
                            } while( $sibling = $sibling->nextSibling );
                            
                            //to get next lines reply text start
                            
                            $comment_arr['reply']['reply_text'] = $reply_txt; //finally pushing the reply text at particular index.
                            
                        }
                        
                        $review_details[] = $comment_arr;
                        
                    }
                }
                
            }
            
            $review['review_details'] = $review_details; // push all values array into single array
                    
        }
        
        $all_reviews[] = $review; // push the review array into final global array
        
    }
    
}

$results_page=get_page_contents('https://uk.trustpilot.com/review/www.graceandthorn.com'); // to get the page contents
search_in_page($results_page); // to form the array of reviews

if( count($all_reviews) > 0 ){
    echo $json = json_encode($all_reviews); //
    
    $unique = rand(1000000, 9999999);
    
    $file = fopen($unique.'.txt', 'w');
    
    if(fwrite($file, $json)){
        echo "File created...";
    }
    
    fclose($file);
    
} else {
    echo "No reviews found!..";
}

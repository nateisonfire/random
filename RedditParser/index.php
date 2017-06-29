<pre><?php

$imgurClientID = ''; // insert your imgur api client id here
$imgurSecret = ''; // insert your imgur api secret here

// save file to image directory
function save_image($url, $name) {
    $image = $url;
    $imageName = 'images/'.$name;
    file_put_contents( $imageName, file_get_contents( $image ) );
}

// read imgur json
function get_imgur_data($url){
    global $imgurClientID;
    $opts = [
        "http" => [
            "method" => "GET",
            "header" => "Authorization: Client-ID ".$imgurClientID
        ]
    ];
    $context = stream_context_create($opts);
    $json = file_get_contents($url, false, $context);
    return json_decode($json);
}

function before ($this, $inthat)
{
    if (strpos($inthat, $this))
        return substr($inthat, 0, strpos($inthat, $this));
    else 
        return $inthat;
}
ob_implicit_flush(true);
ob_start();

// parse reddit
$url = 'https://www.reddit.com/r/popular/.json';
$json = file_get_contents($url);
// decode the json
$children = json_decode($json)->data->children;
$counter = 0;
echo 'Count: '.count($children).'<br />';
foreach($children as $node){
    $counter++;

    $data = $node->data;

    $name = $data->name;
    $name = $data->subreddit.'_'.$name;
    $url = $data->url;

    // if url is a valid image, take it and break (that was easy)
    if (preg_match('/(\.jpg|\.png|\.jpeg|\.gif)$/', $url)) {
        // great! we got what we came for
        // process image then break
        $ext = before('?', pathinfo($url, PATHINFO_EXTENSION));
        save_image($url, $name.'.'.$ext);
        echo 'Saved['.$counter.'] url: '.$name.'.'.$ext.'<br />';
        ob_flush();
        goto end;
    }

    // hmm, no image. but lets just double check to see if it is actually a gif
    if (isset($data->preview->images[0]->variants->gif->source->url)){
        // we have a url! lets use it
        // process image then break
        $gifURL = $data->preview->images[0]->variants->gif->source->url;
        $ext = before('?', pathinfo($gifURL, PATHINFO_EXTENSION));
        save_image($gifURL, $name.'.'.$ext);
        echo 'Saved['.$counter.'] gif: '.$name.'.'.$ext.'<br />';
        ob_flush();
        goto end;
    }

    // what about those imgur albums?
    if (strpos($url, 'imgur.com/a/')){
        $imgurCode = explode('/',parse_url($url)['path'])[2];
        $link = "https://api.imgur.com/3/album/".$imgurCode;
        $imgurImgArray = get_imgur_data($link)->data->images;
        //print_r($imgurImgArray);
        foreach($imgurImgArray as $imgObj){
            //echo $imgObj->link;
            $ext = before('?', pathinfo($imgObj->link, PATHINFO_EXTENSION));
            $imgurName = $name.'_imgur_'.$imgObj->id.'.'.$ext;
            save_image($imgObj->link, $imgurName);
            echo 'Saved['.$counter.'] Imgur: '.$imgurName.'<br />';
            ob_flush();
        }
        goto end;
    }

    // STILL NOTHING? lets just grab a preview
    if (isset($data->preview->images[0]->source->url)){
        $preview = $data->preview->images[0]->source->url;
        $ext = before('?', pathinfo($preview, PATHINFO_EXTENSION));
        save_image($preview, $name.'.'.$ext);
        echo 'Saved['.$counter.'] preview: '.$name.'.'.$ext.'<br />';
        ob_flush();
        goto end;
    }

    // we found nothing of interest
    echo 'Nothing['.$counter.']<br />';

    end:
}


echo '<br />Done';
ob_end_flush(); 

?></pre>
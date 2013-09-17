<?php
/**
 * HTML string in heredoc format for use with
 * sprintf in order to make our code a little
 * less messy
 */
$html = <<<HTML

<li>
    <a href="%s" target="_blank">
        <img src="%s">
        <dl id="bookInfo">
            <dt>%s</dt>
            <dd>%s</dd>
            <dd>View on GoodReads.com</dd>
        </dl>
    </a>
</li>

HTML;
    
    //Developer API key
    $dKey = 'HjeXmA0cB3OA7KdOvV9wVg';
    
    //User ID
    $uId = '21293144';
    
    //Include the class file...though it should be done via
    //autoload opposed to directly including...
    include('goodReads.php');
    $g = isset($_GET['g']) ? $_GET['g'] : 1;
    try{
        //Instantiate with our API key, user ID and any options
        $goodReads = new goodReads($dKey,$uId,array('shelf'=>'read','per_page'=>8),true);
        
        //Grab the shelf array and store it in $books variable
        $books = $goodReads->getShelf();
    }catch(\Exception $e){
        die( 'Error: '.$e->getMessage());
    }

?>
<!DOCTYPE html>
<html>
    <head>
        <title></title>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <link rel="stylesheet" href="shelf.css">
    </head>
    <body>
        <div id="container">
            <div id="shelf">
                <ul>
                    <?php
                    //Loop through the $books. I am doing two loops of four
                    //in order to display two shelves, but it can be done
                    //in any way your heart desires
                    for($i = 0; $i < 4; $i++)
                    {
                        $book = $books[$i];
                        //Output the book list item
                        echo sprintf($html,$book->link,$book->cover,$book->title,$book->author->name);
                    }
                    ?>
                </ul>
            </div>
            <div id="shelf">
                <ul>
                    <?php
                    //Output our second shelf
                    for($i = 4; $i < 8; $i++)
                    {
                        $book = $books[$i];
                        echo sprintf($html,$book->link,$book->cover,$book->title,$book->author->name);
                    }
                    ?>
                </ul>
            </div>
        </div>
    </body>
</html>

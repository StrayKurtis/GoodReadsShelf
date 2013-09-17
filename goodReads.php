<?php
/**
 * goodReads Class
 * 
 * A class that utilizes the API available from goodreads
 * allowing you to grab a shelf of your read/currently reading/want to read
 * books for easy display on a personal web page. Read more!
 * 
 * @author      Kurtis Key <Kurtis@KurtisDesigns.net>
 * @copyright   Copyright (c) 2013, Kurtis Key
 * @link        http://KurtisDesigns.net
 * @license     MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

class goodReads
{
    /**
     * @var string API key
     */
    protected $api;

    /**
     * @var string User ID 
     */
    protected $uID;

    /**
     * Options for gathering shelf.
     * shelf => read|currently-reading|to-read (default=read)
     * sort => title|author|cover|rating|year_pub|date_pub
     *         |date_pub_edition|date_started|date_read|date_updated
     *         |date_added|recommender|avg_rating|num_ratings|review
     *         |read_count|votes|random|comments|notes|isbn|isbn13
     *         |asin|num_pages|format|position|shelves|owned
     *         |date_purchased|purchase_location|condition
     * order => a|d (ascending|descending) (default=d)
     * page => 1-N page number
     * per_page => 1-200 Number of books per page(default = 20)
     * use_gbooks => TRUE|FALSE Use google books images if no-cover? (default TRUE)
     * cache      => TRUE|FALSE cache response? (default TRUE)
     * cache_hours => int hours (default 12)
     * cache_file  => file name of cache file (default goodreads.xml)
     * @var array 
     */
    protected $options = array('shelf'       => 'read',
                                'sort'        => NULL,
                                'order'       => 'd',
                                'page'        => NULL,
                                'per_page'    => 20,
                                'use_gbooks'  => TRUE,
                                'cache'       => TRUE,
                                'cache_hours' => 12,
                                'cache_file'  => 'goodReads.xml',);

    /**
     * Base GoodReads XML URL
     * @var string 
     */
    protected $baseUrl = 'http://www.goodreads.com/review/list/%s.xml?v=2&key=%s&%s';

    /**
     * Base URL used to grab images from Google books
     * @var string 
     */
    protected $gbooksUrl = 'http://books.google.com/books?vid=ISBN%s&printsec=frontcover&img=1&zoom=%s';

    /**
     * The shelf array, returned by self::getShelf() containing books objects
     * @var null|object
     */
    protected $shelf = null;

    /**
     * Constructor
     * @param string $api API key
     * @param string $uID User ID
     * @param array $options Array of options. @see self::$options for possible values
     * @param bool $forceRefresh If file is cached, force refresh, even if not expired?
     * @throws \Exception
     * @throws \InvalidArgumentException
     */
    public function __construct($api, $uID, $options = array(), $forceRefresh = false)
    {
        if(empty($api) || empty($uID))
            throw new \Exception('API key and user ID are required');

        if(!$this->validateApi($api))
            throw new \InvalidArgumentException('Invalid API key');
        
        if(!$this->validateUid($uID))
            throw new \InvalidArgumentException('Invalid user ID');

        if(!is_array($options))
            throw new \InvalidArgumentException('Invalid options argument. Expecting array');

        $this->api = $api;
        $this->uID = $uID;
        $this->options = array_merge($this->options,$options);

        //If we want to force a refresh, check if cache file exists, and delete if it does.
        if($forceRefresh && file_exists($this->options['cache_file']))
        {
            unlink($this->options['cache_file']);
        }
    }

    /**
     * Setter. Allows post-contruct setting of API key, user ID and options array
     * @param string $key Property name(api, uID or options)
     * @param mixed $val Value(s)
     */
    public function __set($key,$val)
    {
        if(in_array($key,array('api','uID')))
        {
            $this->$key = $val;
        }
        elseif($key == 'options' && is_array($val))
        {
            $this->options = array_merge($this->options,$val);
        }
    }

    /**
     * Attempt to validate User ID (At least make sure it is numeric)
     * @param string $uid User ID
     * @return bool
     */
    protected function validateUid($uid)
    {
        return (bool)preg_match('#^[0-9]+$#',$uid);
    }

    /**
     * Validate API key
     * @param string $api API key
     * @return bool Is API key valid???
     */
    protected function validateApi($api)
    {
        return (bool)preg_match('#^[a-zA-Z0-9]+$#', $api);
    }

    /**
     * Generate the cache file
     * @param \SimpleXMLElement
     * @throws \Exception
     */
    protected function generateCache($xmlObj)
    {
        if(!$xmlObj->asXML($this->options['cache_file']))
            throw new \Exception('Could not write cache file');
    }

    /**
     * Parse either local or remote XML file
     * @param string $file
     * @return \SimpleXMLElement
     */
    protected function getXML($file)
    {
        if(!$xml = @simplexml_load_file($file, 'SimpleXMLElement', LIBXML_NOCDATA))
            throw new \Exception('Could not parse XML');

        return $xml;
    }

    /**
     * Populate $book shelf object
     * @return array Array of book objects
     */
    protected function populateShelf()
    {
        //Do we want to cache?
        if($this->options['cache'])
        {
            $file = $this->options['cache_file'];
            $time = file_exists($file) ? filemtime($file) : 0;
            $expire = time() - ($this->options['cache_hours'] * 60 * 60);
            
            //File exists and is not expired?
            if(file_exists($file) && $time > $expire)
            {
                $xml = $this->getXML($file);
            }
            else
            {
                //File does not exist or is expired. Generate!
                $xml = $this->getXML($this->generateURL());
                $this->generateCache($xml);
            }
        }
        else
        {
            //We do not want to cache...
            $xml = $this->getXML($this->generateURL());
        }
        
        //Populate the $shelf array with $book objects. Unfortunatly, SimpleXML does not play nice, so
        //We have to manually type cast each property to a string in order to avoid
        //any issues trying to use them as strings when they are actually SimpleXMLElement Objects.
        foreach($xml->reviews->children() as $review)
        {
            $book = new stdClass();
            $book->shelf = (string)$review->shelves->shelf->attributes()->name;
            $book->title = (string)$review->book->title;
            $book->link = (string)$review->book->link;
            $book->author = new stdClass();
            $book->author->name = (string)$review->book->authors->author->name;
            $book->author->image = (string)$review->book->authors->author->image_url;
            $book->author->image_small = (string)$review->book->authors->author->small_image_url;
            $book->author->link = (string)$review->book->authors->author->link;
            $book->description = (string)(empty($review->book->description) ? 'Unknown' : strip_tags($review->book->description));
            $book->format = (string)(empty($review->book->format) ? 'Unknown' : $review->book->format);
            $book->pages = (string)(empty($review->book->num_pages) ? 'Unknown' : $review->book->num_pages);
            $book->isbn = (string)(is_object($review->book->isbn) ? $review->book->isbn13 : $review->book->isbn);
            $book->id = (string)$review->id;
            
            //If the cover returns as 'nocover' and we want to use Google Books, set up the URL's
            if(strpos($review->book->image_url,'nocover') && $this->options['use_gbooks'] === TRUE)
            {
                $book->cover = sprintf($this->gbooksUrl,$book->isbn,1);
                $book->cover_small = sprintf($this->gbooksUrl,$book->isbn,5);
            }
            else
            {
                (string)$book->cover = $review->book->image_url;
                (string)$book->cover_small = $review->book->small_image_url;
            }
            
            $this->shelf[] = $book;
        }

        return $this->shelf;
    }
    
    /**
     * Generate the request URL by extracting the options we need from the self::$options
     * array and building a query URL.
     * @return string formatted URL
     */
    protected function generateURL()
    {
        $options = http_build_query(array_slice($this->options,0,5));
        $this->baseUrl = sprintf($this->baseUrl,$this->uID,$this->api,$options);
        return $this->baseUrl;
    }
    
    /**
     * Determiness if the shelf is empty, if so, populate. Return shelf array
     * @return array Array of book objects
     */
    public function getShelf()
    {
        return (is_null($this->shelf) ? $this->populateShelf() : $this->shelf);
    }
}
<?php
/* 
| --------------------------------------------------------------
| 
| Frostbite Framework
|
| --------------------------------------------------------------
|
| Author:       Steven Wilson
| Copyright:    Copyright (c) 2011, Steven Wilson
| License:      GNU GPL v3
|
| ---------------------------------------------------------------
| Class: Router
| ---------------------------------------------------------------
|
| This class is used to determine our controller / action. It is
| also used for module checking, or spitting out 404's
|
*/
namespace Core;

class Router
{
    // Have we routed the url yet?
    public $routed = false;
    
    // Our http protocol (https or http)
    protected $protocol;
    
    // Our hostname
    protected $http_host;
    
    // Our Site URL
    protected $site_url;
    
    // The requested URI
    protected $uri;
    
    // Our site directory
    protected $site_dir;
    
    // Our controller name
    protected $controller;

    // Our action (sub page)
    protected $action;

    // The querystring
    protected $queryString;
 
/*
| ---------------------------------------------------------------
| Constructor
| ---------------------------------------------------------------
|
*/ 
    public function __construct()
    {
        // Load the config and input class'
        $this->config = load_class('Config');
        $this->input = load_class('Input');
        
        // Add trace for debugging
        \Debug::trace('Router class initialized successfully', __FILE__, __LINE__);
    }

/*
| ---------------------------------------------------------------
| Method: route_url()
| ---------------------------------------------------------------
|
| This method analyzes the url to determine the controller / action
| and query string
|
| @Return (Array) Returns an array of controller, action and queryString
|
*/
    public function route_url() 
    {
        // Add trace for debugging
        \Debug::trace('Routing url...', __FILE__, __LINE__);
        
        // Determine our http hostname, and site directory
        $this->http_host = rtrim($_SERVER['HTTP_HOST'], '/');
        $this->site_dir = dirname( $_SERVER['PHP_SELF'] );
        
        // Detect our protocol
        if(isset($_SERVER['HTTPS']))
        {
            if(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off')
            {
                $this->protocol = 'https';
            }
            else
            {
                $this->protocol = 'http';
            }
        }
        else
        {
            $this->protocol = 'http';
        }
        
        // Build our Full Base URL
        $site_url = $this->http_host .'/'. $this->site_dir;
        while(strpos($site_url, '//') !== FALSE) $site_url = str_replace('//', '/', $site_url);
        $this->site_url = str_replace( '\\', '', $this->protocol .'://' . rtrim($site_url, '/') );

        // Process the site URI
        if( !$this->config->get('enable_query_strings', 'Core'))
        {
            // Get our current url, which is passed on by the 'url' param
            $this->uri = (isset($_GET['url']) ? $this->input->get('url', TRUE) : '');   
        }
        else
        {
            // Define our needed vars
            $c_param =  $this->config->get('controller_param', 'Core');
            $a_param = $this->config->get('action_param', 'Core');
            
            // Make sure we have a controller at least
            $c = $this->input->get($c_param, TRUE );
            if( !$c )
            {
                $this->uri = '';
            }
            else
            {
                // Get our action
                $a = $this->input->get( $a_param, TRUE );
                if( !$a ) $a = $this->config->get('default_action', 'Core'); // Default Action
                
                // Init the uri
                $this->uri = $c .'/'. $a;
                
                // Clean the query string
                $qs = $this->input->clean( $_SERVER['QUERY_STRING'] );
                $qs = explode('&', $qs);
                foreach($qs as $string)
                {
                    // Convert this segment to an array
                    $string = explode('=', $string);
                    
                    // Dont add the controller / action twice ;)
                    if($string[0] == $c_param || $string[0] == $a_param) continue;
                    
                    // Append the uri vraiable
                    $this->uri .= '/'. $string[1];
                }
            }
        }
        
        // If the URI is empty, then load defaults
        if(empty($this->uri)) 
        {
            // Set our Controller / Action to the defaults
            $controller = $this->config->get('default_controller', 'Core'); // Default Controller
            $action = $this->config->get('default_action', 'Core'); // Default Action
            $queryString = array(); // Default query string
        }
        
        // There is a URI, Lets load our controller and action
        else 
        {
            // Remove any left slashes or double slashes
            $this->uri = ltrim( str_replace('//', '/', $this->uri), '/');

            // We will start by bulding our controller, action, and querystring
            $urlArray = array();
            $urlArray = explode("/", $this->uri);
            $controller = $urlArray[0];
            
            // If there is an action, then lets set that in a variable
            array_shift($urlArray);
            if(isset($urlArray[0]) && !empty($urlArray[0])) 
            {
                $action = $urlArray[0];
                array_shift($urlArray);
            }
            
            // If there is no action, load the default 'index'.
            else 
            {
                $action = $this->config->get('default_action', 'Core'); // Default Action
            }
            
            // $queryString is what remains
            $queryString = $urlArray;
        }
        
        // Tell the system we've routed
        $this->routed = true;  
        
        // Make sure the first character of the controller is not an _ !
        if( strncmp($controller, '_', 1) == 0 || strncmp($action, '_', 1) == 0 )
        {
            show_404();
        }
        
        // Set static Variables
        $this->controller = $controller;
        $this->action = $action;
        $this->queryString = $queryString;
        
        // Add trace for debugging
        \Debug::trace("Url routed successfully. Found controller: {$this->controller}; Action: {$this->action}; Querystring: ". implode('/', $this->queryString), __FILE__, __LINE__);
    }

/*
| ---------------------------------------------------------------
| Method: get_url_info()
| ---------------------------------------------------------------
|
| This method returns all the url information
|
| @Return (Array) Returns an array of all url related info
|
*/    
    public function get_url_info()
    {
        // Make sure we've at least routed the url here;
        if(!$this->routed) $this->route_url();
        return array(
            'protocol' => $this->protocol,
            'http_host' => $this->http_host,
            'site_url' => $this->site_url,
            'site_dir' => $this->site_dir,
            'uri' => $this->uri,
            'controller' => $this->controller,
            'action' => $this->action,
            'querystring' => $this->queryString
        );
    }
    
/*
| ---------------------------------------------------------------
| Method: get_uri_segement()
| ---------------------------------------------------------------
|
| This method returns the specified URI segement
|
| @Return (String) Returns the segement, or FALSE
|
*/    
    public function get_uri_segement($index)
    {
        // Return the URI
        if(isset($this->uri[$index]))
        {
            return $this->uri[$index];
        }
        return FALSE;
    }
}
// EOF
<?php

/**
 * FIZZY
 * A micro cms
 *
 * @author Mattijs Hoitink <mattijshoitink@gmail.com>
 * @copyright Copyright (c) 2009 Mattijs Hoitink
 * @license http://opensource.org/licenses/mit-license.php The MIT License
 * @package fizzy
 */

# ============================================================================ #
#    1. BASE                                                                   #
# ============================================================================ #
define('FIZZY', '0.2');

/**
 * The pages document
 * @var DOMDocument
 */
$pagesDocument = null;

/**
 * The loaded config
 * @var SimpleXMLElement
 */
$config = null;

/**
 * Shake it up, run the CMS.
 */
function shake()
{
    load_config();
    load_routes();
    load_pages();
    
    // Run limonade
    run();
}

/**
 * Loads the config file defined by the CONFIG_FILE constant.
 */
function load_config()
{
    global $config;
    
    check_file(CONFIG_FILE);
    $config = simplexml_load_file(CONFIG_FILE);//
    if(false === $config) { halt(SERVER_ERROR, 'Could not load config file.'); }
}

/**
 * Loads the routes from the CONFIG_FILE
 */
function load_routes()
{
    global $config;
    if(null === $config) { load_config(); }
    
    $routes = $config->xpath('/fizzy/application/routes/route');
    $backendSwitch = text_node($config, '/fizzy/application/backendSwitch');
    
    foreach($routes as $route) {
        $routePattern = str_replace('BACKEND', $backendSwitch, (string) $route);
        $destination = (string) $route['destination'];
        switch(strtoupper((string) $route['type'])) {
            case 'GET':
                dispatch_get($routePattern, $destination);
                break;
            case 'POST':
                dispatch_post($routePattern, $destination);
                break;
            case 'BOTH':
                dispatch_get($routePattern, $destination);
                dispatch_post($routePattern, $destination);
                break;
        }
    }
}

/**
 * Loads page data from the pages XML file. This file is defined by the 
 * PAGES_FILE constant.
 */
function load_pages()
{
    global $pagesDocument;
    
    check_file(PAGES_FILE);
    $pagesDocument = new DOMDocument('1.0', 'UTF-8');
    $pagesDocument->loadXML(file_get_contents(PAGES_FILE));
}

/**
 * Configure options for limonade. Will be called when limonade is run.
 */
function configure()
{
    global $config;
    
    // load options from config
    option('env', constant("ENV_" . strtoupper(text_node($config, '/fizzy/application/env'))));
    option('backend_switch', text_node($config, '/fizzy/application/backendSwitch'));
    option('frontend_layout', text_node($config, "/fizzy/application/layouts/layout[@name='frontend']"));
    option('backend_layout', text_node($config, "/fizzy/application/layouts/layout[@name='backend']"));
    
    // load options from constants
    option('root_dir', ROOT_DIR);
    option('lib_dir', ROOT_DIR . '/lib');
    option('views_dir', VIEWS_DIR);
    option('public_dir', PUBLIC_DIR);
    option('base_url', BASE_URL);

    // load composed options
    option('limonade_dir', option('lib_dir'). '/limonade/');
    option('fizzy_dir', option('lib_dir'). '/fizzy/');
    option('backend_url', option('base_url') . '/' . option('backend_switch'));
}

# ============================================================================ #
#    2. ACTIONS                                                                #
# ============================================================================ #

## frontpage controller actions ________________________________________________

/**
 * Shows the homepage
 * @return string
 */
function f_homepage()
{
    $domElement = query_pages("/pages/page[@isHomepage='true']", true);
    $page = element_to_array($domElement);
    return render_page($page);
}

/**
 * Shows a page by it's slug.
 * @param string $slug
 * @return string
 */
function f_by_slug()
{
    $slug = params('slug');
    $domElement = query_pages("/pages/page[slug='{$slug}']", true);
    $page = element_to_array($domElement);
    return render_page($page);
}

/**
 * Shows a page by it's uid.
 * @param string $uid
 * @return string
 */
 
function f_by_uid()
{
    $uid = params('uid');
    $page = find_page($uid);
    return render_page($page);
}

## backpage controller actions _________________________________________________

/**
 * Shows the backend dashboad.
 * @return string
 */
function b_dashboard()
{
    $pageNodes = query_pages("/pages/page");
    $pages = array();
    foreach($pageNodes as $node) {
        if($node instanceof DOMElement) {
            $pages[] = element_to_array($node);
        }
    }
    return render_backend('dashboard.phtml', array('pages' => $pages));
}

function b_add_page()
{
    global $pagesDocument;
    if(request_is_post()) {
        $element = page_from_post($_POST);
        //add_page($element);
        //save_pages();
        var_dump($pagesDocument->saveXML($element));
        //redirect('http://www.google.com');
    }
    
    $layouts = get_layouts();
    
    return render_backend('form.phtml', array('layouts' => $layouts));
}

function b_edit_page()
{
    $uid = params('uid');
    $page = find_page("/pages/page[@uid='{$uid}']");
    
    if(request_is_post()) {
        
        if(null === $page) {
            $element = create_page($_POST);
        }
    }
    
    $vars = array();
    if(null !== $page) {
        $vars['page'] = page_to_array($page);
    }
    return render_backend('form.phtml', $vars);
}

function b_delete_page()
{
    echo "b_delete_page";
}

# ============================================================================ #
#    3. RENDER                                                                 #
# ============================================================================ #

/**
 * Renders a page in it's layout. The page data can be presented to this 
 * function as an array or a DOMElement. DOMElements will be converted to an 
 * array.
 * @param array|DOMElement $page
 * @return string
 */
function render_page($page)
{
    if($page instanceof DOMElement) {
        $page = element_to_array($page);
    }

    if(!isset($page['template'])) {
        if(file_exists(option('views_dir') . 'page.phtml')) {
            // check for default template in VIEWS_DIR
            $view = option('views_dir') . 'page.phtml';
        } else {
            // use the fizzy template
            $view = option('fizzy_dir') . 'page.phtml';
        }
    } 
    else {
        $view = $page['template'];
    }
    
    if(isset($page['layout'])) {
        $layout = $page['layout'];
    } else {
        $layout = null;
    }
    
    return render($view, $layout, array('page' => $page));
}

/**
 * Renders a view file in the frontend layout
 * @param string $view
 * @param array $vars
 * @return string
 */
function render_frontend($view, $vars)
{
    return render("frontend/{$view}", option('frontend_layout'), $vars);
}

/**
 * Renders a view file in the backend layout
 * @param string $view
 * @param array $locals
 * @return string
 */
function render_backend($view, $locals = array())
{
    option('views_dir', option('fizzy_dir'));
    $rendered = render($view, 'layout.phtml', $locals);
    option('views_dir', VIEWS_DIR);
    return $rendered;
}

# ============================================================================ #
#    4. PAGE FUNCTIONS                                                         #
# ============================================================================ #

/**
 * Finds a page by uid
 * @param string $uid
 * @return DOMElement
 */
function find_page($uid) 
{
    $nodeList = query_pages("/pages/page[@uid='{$uid}']");
    if($nodeList->length === 0) {
        die("Page with uid {$uid} not found.");
    }
    
    return $nodeList->item(0);
}

/**
 * Queries the pages document via XPath
 * @param string $xpath
 * @param boolean $firstOnly
 * @return DOMNodeList|DOMElement
 */
function query_pages($query, $firstOnly = false) 
{
    global $pagesDocument;
    $xpath = new DOMXPath($pagesDocument);
    $list = $xpath->query($query);
    if($firstOnly && $list->length > 0) {
        return $list->item(0);
    } else {
        return $list;
    }
}

/**
 * Creates a page DOMElement from a $_POST array.
 * @param array $post
 * @return DOMElement
 * @todo clean input
 */
function page_from_post($post)
{
    $data = array();
    $data['uid'] = md5(mktime());
    $data['title'] = $post['title'];
    $data['slug'] = $post['slug'];
    $data['body'] = $post['body'];
    $data['layout'] = $post['layout'];
    $data['homepage'] = isset($post['homepage']) ? 'true' : 'false';
    
    return array_to_element($data);
}

/**
 * Saves the pages document to the PAGES_FILE. If the file is not writable or 
 * the XML is not valid the system will be halted.
 */
function save_pages()
{

}

/**
 * Converts a DOMElement containing page data to an array.
 * @param DOMElement $element
 * @return array
 * */
function element_to_array(DOMElement $element)
{
    $page = array();
    // Loop the children
    foreach($element->childNodes as $child) {
        if($child instanceof DOMElement) {
            $page[$child->nodeName] = $child->nodeValue;
        }
    }
    // Loop the attributes
    foreach($element->attributes as $attribute) {
        $page[$attribute->name] = $attribute->value;
    }
    return $page;
}

/**
 * Converts an array containing page data to a DOMElement.
 * @param array $data
 * @return DOMElement
 */
function array_to_element(array $data)
{
    global $pagesDocument;
    
    $page = $pagesDocument->createElement('page');
    $page->setAttribute('uid', $data['uid']);\
    $page->setAttribute('isHomepage', $data['homepage']);
    $page->appendChild($pagesDocument->createElement('title', $data['title']));
    $page->appendChild($pagesDocument->createElement('slug', $data['slug']));
    
    $body = $pagesDocument->createElement('body');
    $body->appendChild($pagesDocument->createCDATASection($data['body']));
    $page->appendChild($body);
    
    if(isset($data['layout']) && !empty($data['layout'])) {
        $page->appendChild($pagesDocument->createElement('layout', $data['layout']));
    }
    
    return $page;
}


# ============================================================================ #
#    5. UTIL                                                                 #
# ============================================================================ #

/**
 * Check if a file exists and is readable.
 * @param string $file
 */
function check_file($file)
{
    if(!file_exists($file) || !is_readable($file)) {
        halt();
    }
}

/**
 * Gets a text node by xpath query
 * @param SimpleXMLElement $xml
 * @param string $xpath
 * @return string
 */
function text_node($xml, $xpath)
{
    $node = '';

    $nodes = $xml->xpath($xpath);
    if(is_array($nodes) && count($nodes) === 1) {
        $node = (string) array_shift($nodes);
    }
    
    return $node;
}

/**
 * Fetches the defined layouts from the config file.
 * @todo check the defined views_dir for files with the format layout.[name].phtml
 * @return array
 */
function get_layouts()
{
    global $config;
    $layouts = array();
    
    $layoutNodes = $config->xpath('/fizzy/application/layouts/layout');
    foreach($layoutNodes as $node) {
        $layouts[(string) $node['name']] = (string) $node;
    }
    
    return $layouts;
}

/**
 * Cleans an input variable
 * @param string $var
 * @return string
 * */
function clean($var)
{
    $clean_var = $var;
    
    return $clean_var;
}


?>
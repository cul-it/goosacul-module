<?php
/*
google_cul.php - google search appliance handler for cornell university library

-jgr25

http://web.search.cornell.edu/search?q=three+blind+mice
&btnG=go&output=xml&sort=date%3AD%3AL%3Ad1&ie=UTF-8
&client=default_frontend&oe=UTF-8
&site=default_collection&sitesearch=cornell.edu

KeyMatch example:
<GM>
<GL>http://www.clal.cornell.edu/</GL>
<GD>Cornell Language Acquisition Laboratory</GD>
</GM>
<GM>
<GL>http://www.arts.cornell.edu/russian/index.html</GL>
<GD>Russian Literature</GD>
</GM>

Note: 11/3/2011
	base url used to be http://web.search.cornell.edu/search
	now the base url is http://www.google.com/cse
	
	Added parameters:
		cx=012404574275687773960:xo00k3nliga
		client=google-csbe
	
	keep these parameters:
		output=xml_no_dtd
		ie=UTF-8
		oe=UTF-8
		
	and for the 'libraries' collection (to search all library web sites)
	you need to add 'more:libraries' to the q parameter, but separate it
	from the parameter value with a space
	
	?q=three+blind+mice more:libraries


*/
class cul_gsa_search {
	var $params = array();
	var $results = array();
	var $keymatch_results = array();  // GM (GL, GD)
	var $search_time; // TM
	var $query;
	var $starting;
	var $ending;
	var $total_count;
	var $query_next_page;
	var $query_previous_page;
	var $error;
	// parser context
	var $cur_keymatch = array();
	var $cur = array();
	var $cur_text;
	var $capture;
	var $query_parameter;
	
	function cul_gsa_search($query_parameter = 'qp') {
		//q,btnG,output,sort,ie,gsa_client,oe,site,proxyreload,sitesearch,start
		$this->params = array(
			"q" => "",
			"output" => "xml_no_dtd",
			"sort" => "",
			"ie" => "UTF-8",
			"oe" => "UTF-8",
			"site" => "default_collection",
			"as_sitesearch" => "library.cornell.edu",
			"filter" => "1",	// both by default
			"cx" => "012404574275687773960:xo00k3nliga",
			"client" => "google-csbe",
			);		
		$this->error = false;
		$this->query_parameter = $query_parameter;
		}
		
	function setSite($sitesearch = "library.cornell.edu") {
		if (empty($sitesearch)) {
			unset($this->params["as_sitesearch"]);
			}
		else {
			$this->params["as_sitesearch"] = $sitesearch;
			}
		}
		
	function setCollection($collection = "default_collection") {
		$this->params["site"] = $collection;
		}
		
	function setStartingResult($starting_result = 0) {
		$this->params["start"] = $starting_result;
		}
		
	function setResultCount($num_results = 10) {
		$this->params["num"] = $num_results;
		}
		
	function setQueryCharset($charset = "UTF-8") {
		$this->params["ie"] = $num_results;
		}
		
	function setResultCharset($charset = "UTF-8") {
		$this->params["oe"] = $num_results;
		}
		
	function setLanguageFilter($lang = "lang_en") {
		// see http://code.google.com/gsa_apis/xml_reference.html#request_subcollections_auto
		$this->params["lr"] = $lang;
		}
		
	function setFiltering($filter = 1) {
		// results filtering
		switch ($filter) {
			case "1": //  	Duplicate Snippet Filter on, Duplicate Directory Filter on
			case "0": //  	Duplicate Snippet Filter off, Duplicate Directory Filter off
			case "s": //  	Duplicate Snippet Filter off, Duplicate Directory Filter on
			case "p": //  	Duplicate Snippet Filter on, Duplicate Directory Filter off
				$this->params["filter"] = $filter;
				break;
			default:
				$this->params["filter"] = 1;
				break;
				}
		}
		
	function search($query, $collection = FALSE) {
		$this->query = $query;
		if (strcmp($this->params["site"],'default_collection') == 0) {
			$this->params["q"] = $query;
			}
		else {
			$this->params["q"] = $query . " more:" . $this->params["site"];
			}
		$this->error = false;
		$allswell = true;
		
		$url = "http://www.google.com/cse?" . http_build_query($this->params, '', '&');
		
		// Create Expat parser
		$parser = xml_parser_create("UTF-8");
		
		// Set handler functions
		xml_set_element_handler($parser, array(&$this, "start_element"), array(&$this, "stop_element"));
		xml_set_character_data_handler($parser, array(&$this, "char_data"));
		xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
	
		// Parse the file
		$ret = parse_from_url($parser, $url);
		if(!$ret) {
			$allswell = false;
		    $this->error = sprintf("XML error: %s at line %d",
		                    xml_error_string(xml_get_error_code($parser)),
		                    xml_get_current_line_number($parser));
			$this->error .= " " . $url;
			$this->error .= " \n" . print_r($this,TRUE);
			}
		
		// Free parser
		xml_parser_free($parser);
		
		return $allswell;
		}
		
	function getKeymatchResults() {
		return $this->keymatch_results;
		}
		
	function getResults() {
		return $this->results;
		}
	
	function getTime($format = "%.2f") {
		return sprintf($format, $this->search_time);
		}
		
	function getNextURL() {
		return $this->query_next_page;
		}
		
	function getPreviousURL() {
		return $this->query_previous_page;
		}
		
	function getStartingResultNumber() {
		return $this->starting;
		}
		
	function getEndingResultNumber() {
		return $this->ending;
		}
		
	function getResultsCount() {
		return $this->total_count;
		}
	
	function getError() {
		return $this->error;
		}
	
	function start_element($parser, $name, $attrs) {
		switch ($name) {
			case "RES":
				$this->starting = $attrs["SN"];
				$this->ending = $attrs["EN"];
				$this->cur = array();
				break;
			case "R":
				$this->cur["N"] = $attrs["N"];
				break;
			case "GM":
				$this->cur_keymatch = array();
				break;
			case "GL":
			case "GD":
			case "TM":
			case "NU":
			case "PU":
			case "M":
			case "U":
			case "S":
			case "T":
				$this->capture = true;
				break;
			}
		}
	
	function integrated_path($gsa_path) {
		// reconcile Drupal path with gsa string
		
		// clean out any collection string
		$collection = " more:" . $this->params["site"];
		$gsa_path = str_replace($collection, '', $gsa_path);
		
		// gsa path is /search?q=searchfor&site=bla...
		$my_query_string = str_replace('/custom?q=', 'qp=', $gsa_path);
		
		$gsa_query_string = substr($gsa_path, strpos($gsa_path, "?") + 1);
		$gsa_query = array();
		parse_str($gsa_query_string, $gsa_query);
			
		// change the q parameter to $this->query_parameter so it won't conflict with non-clean urls
		if (isset($gsa_query['q'])) {
			$gsa_query['q'] = str_replace($collection, '', $gsa_query['q']);
			$gsa_query[$this->query_parameter] = $gsa_query['q'];
			unset($gsa_query['q']);
			}
			
		$page_path = isset($_GET['q']) ? $_GET['q'] : '<front>';
		$link = url($page_path, array('absolute' => TRUE));
		$parts = parse_url($link);
		if (isset($parts['query'])) {
			$parts['query'] .= '&' . $my_query_string;
			}
		else {
			$parts['query'] = $my_query_string;
			}
		$newlink = $parts['scheme'] . '://' . $parts['host'];
		if (!empty($parts['port'])) $newlink .= ':' . $parts['port'];
		if (!empty($parts['path'])) $newlink .= $parts['path'];
		if (!empty($parts['query'])) $newlink .= '?' . $parts['query'];
		if (!empty($parts['fragment'])) $newlink .= '#' . $parts['fragment'];
		return $newlink;
		}
	
	function stop_element($parser, $name) {
		switch ($name) {
			case "R":
				$this->results[] = $this->cur;
				unset($this->cur);
				break;
			case "M":
				$this->total_count = $this->cur_text;
				$this->cur_text = "";
				$this->capture = false;
				break;
			case "NU":
				$this->query_next_page = $this->integrated_path($this->cur_text);
				$this->cur_text = "";
				$this->capture = false;
				break;
			case "PU":
				$this->query_previous_page = $this->integrated_path($this->cur_text);
				$this->cur_text = "";
				$this->capture = false;
				break;
			case "U":
			case "S":
			case "T":
				$this->cur["$name"] = $this->cur_text;
				$this->cur_text = "";
				$this->capture = false;
				break;
			case "TM":
				$this->search_time = $this->cur_text;
				$this->cur_text = "";
				$this->capture = false;
				break;
			case "GM":
				$this->keymatch_results[] = $this->cur_keymatch;
				break;
			case "GL":
			case "GD":
				$this->cur_keymatch["$name"] = $this->cur_text;
				$this->cur_text = "";
				$this->capture = false;
				break;
			}
		}
	
	function char_data($parser, $data) {
		if ($this->capture)
			$this->cur_text .= $data;
		}
	}	
	
function parse_from_url($parser, $url) {
    if(!($fp = @fopen($url, "r"))) 
    {
        die("Can't open \"$url\".");
    }
    
	$contents = '';
    while($data = fread($fp, 4096))
    {
		$contents .= $data;
	}
    
    fclose($fp);
	
    if(!xml_parse($parser, $contents))
        {
            return(false);
        }
    
    return(true);
	}

?>
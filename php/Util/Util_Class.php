<?php
/*
 * $Id: Util_Class.php 53 2011-07-13 16:28:46Z genghishack $
 * 
 * Util_1.1.php 
 * 
 * Useful, miscellaneous functions.  This will get split out into smaller, categorized utility files once it grows large enough.
 * 1.1: added file_exists_in_path
 */

class Util_Class
{
    /**
     * This function is to be used instead of include() when you have to include a file that renders 
     * HTML straight to the page (like when you're using someone else's code and you can't refactor it 
     * for whatever reason...) and you want TOTAL! COMPLETE! CONTROL! of what gets output to the page, when.  
     */
    static public function get_include_contents($sFileName)
    {
        if (is_file($sFileName)) {
            ob_start();
            include $sFileName;
            $contents = ob_get_contents();
            ob_end_clean();
            return $contents;
        }
        return false;
    }
    
    /**
     * xml2array() will convert the given XML text to an array in the XML structure.
     * Link: http://www.bin-co.com/php/scripts/xml2array/
     * Arguments : $contents - The XML text
     *             $get_attributes - 1 or 0. If this is 1 the function will get the attributes as well as the tag values - this results in a different array structure in the return value.
     *             $priority - Can be 'tag' or 'attribute'. This will change the way the resulting array sturcture. For 'tag', the tags are given more importance.
     * Return: The parsed XML in an array form. Use print_r() to see the resulting array structure.
     * Examples: $array =  xml2array(file_get_contents('feed.xml'));
     *           $array =  xml2array(file_get_contents('feed.xml', 1, 'attribute'));
     */
    static public function xml2array($contents, $get_attributes=1, $priority = 'tag') 
    {
        if(!$contents) return array();
        
        if(!function_exists('xml_parser_create')) {
            //print "'xml_parser_create()' function not found!";
            return array();
        }
        
        //Get the XML parser of PHP - PHP must have this module for the parser to work
        $parser = xml_parser_create('');
        xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, "UTF-8"); # http://minutillo.com/steve/weblog/2004/6/17/php-xml-and-character-encodings-a-tale-of-sadness-rage-and-data-loss
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
        xml_parse_into_struct($parser, trim($contents), $xml_values);
        xml_parser_free($parser);
        
        if(!$xml_values) return;//Hmm...
        
        //Initializations
        $xml_array = array();
        $parents = array();
        $opened_tags = array();
        $arr = array();
        
        $current = &$xml_array; //Refference
        
        //Go through the tags.
        $repeated_tag_index = array();//Multiple tags with same name will be turned into an array
        foreach($xml_values as $data) 
        {
            unset($attributes,$value);//Remove existing values, or there will be trouble
            
            //This command will extract these variables into the foreach scope
            // tag(string), type(string), level(int), attributes(array).
            extract($data);//We could use the array by itself, but this cooler.
            
            $result = array();
            $attributes_data = array();
            
            if(isset($value)) {
                if($priority == 'tag') $result = $value;
                else $result['value'] = $value; //Put the value in a assoc array if we are in the 'Attribute' mode
            }
  
            //Set the attributes too.
            if(isset($attributes) and $get_attributes) {
                foreach($attributes as $attr => $val) {
                    if($priority == 'tag') $attributes_data[$attr] = $val;
                    else $result['attr'][$attr] = $val; //Set all the attributes in a array called 'attr'
                }
            }
  
            //See tag status and do the needed.
            if($type == "open") {//The starting of the tag '<tag>'
                $parent[$level-1] = &$current;
                if(!is_array($current) or (!in_array($tag, array_keys($current)))) { //Insert New tag
                    $current[$tag] = $result;
                    if($attributes_data) $current[$tag. '_attr'] = $attributes_data;
                    $repeated_tag_index[$tag.'_'.$level] = 1;
                    
                    $current = &$current[$tag];
                    
                } else { //There was another element with the same tag name
  
                    if(isset($current[$tag][0])) {//If there is a 0th element it is already an array
                        $current[$tag][$repeated_tag_index[$tag.'_'.$level]] = $result;
                        $repeated_tag_index[$tag.'_'.$level]++;
                    } else {//This section will make the value an array if multiple tags with the same name appear together
                        $current[$tag] = array($current[$tag],$result);//This will combine the existing item and the new item together to make an array
                        $repeated_tag_index[$tag.'_'.$level] = 2;
                      
                        if(isset($current[$tag.'_attr'])) { //The attribute of the last(0th) tag must be moved as well
                            $current[$tag]['0_attr'] = $current[$tag.'_attr'];
                            unset($current[$tag.'_attr']);
                        }
  
                    }
                    $last_item_index = $repeated_tag_index[$tag.'_'.$level]-1;
                    $current = &$current[$tag][$last_item_index];
                }
  
            } elseif($type == "complete") { //Tags that ends in 1 line '<tag />'
                //See if the key is already taken.
                if(!isset($current[$tag])) { //New Key
                    $current[$tag] = $result;
                    $repeated_tag_index[$tag.'_'.$level] = 1;
                    if($priority == 'tag' and $attributes_data) $current[$tag. '_attr'] = $attributes_data;
  
                } else { //If taken, put all things inside a list(array)
                    if(isset($current[$tag][0]) and is_array($current[$tag])) {//If it is already an array...
                    
                        // ...push the new element into that array.
                        $current[$tag][$repeated_tag_index[$tag.'_'.$level]] = $result;
                      
                        if($priority == 'tag' and $get_attributes and $attributes_data) {
                            $current[$tag][$repeated_tag_index[$tag.'_'.$level] . '_attr'] = $attributes_data;
                        }
                        $repeated_tag_index[$tag.'_'.$level]++;
                        
                    } else { //If it is not an array...
                        $current[$tag] = array($current[$tag],$result); //...Make it an array using using the existing value and the new value
                        $repeated_tag_index[$tag.'_'.$level] = 1;
                        if($priority == 'tag' and $get_attributes) {
                            if(isset($current[$tag.'_attr'])) { //The attribute of the last(0th) tag must be moved as well
                              
                                $current[$tag]['0_attr'] = $current[$tag.'_attr'];
                                unset($current[$tag.'_attr']);
                            }
                          
                            if($attributes_data) {
                                $current[$tag][$repeated_tag_index[$tag.'_'.$level] . '_attr'] = $attributes_data;
                            }
                        }
                        $repeated_tag_index[$tag.'_'.$level]++; //0 and 1 index is already taken
                    }
                }
                
            } elseif($type == 'close') { //End of tag '</tag>'
                $current = &$parent[$level-1];
            }
        }
        
        return($xml_array);
    }
    
    static public function getUSStateArray($options = array())
    {
        // TODO: 'options' will include things like whether to include territories, protectorates, etc
        
        $rStates = array(
            'AL'=>"Alabama",  
            'AK'=>"Alaska",  
            'AZ'=>"Arizona",  
            'AR'=>"Arkansas",  
            'CA'=>"California",  
            'CO'=>"Colorado",  
            'CT'=>"Connecticut",  
            'DE'=>"Delaware",  
            'DC'=>"District Of Columbia",  
            'FL'=>"Florida",  
            'GA'=>"Georgia",  
            'HI'=>"Hawaii",  
            'ID'=>"Idaho",  
            'IL'=>"Illinois",  
            'IN'=>"Indiana",  
            'IA'=>"Iowa",  
            'KS'=>"Kansas",  
            'KY'=>"Kentucky",  
            'LA'=>"Louisiana",  
            'ME'=>"Maine",  
            'MD'=>"Maryland",  
            'MA'=>"Massachusetts",  
            'MI'=>"Michigan",  
            'MN'=>"Minnesota",  
            'MS'=>"Mississippi",  
            'MO'=>"Missouri",  
            'MT'=>"Montana",
            'NE'=>"Nebraska",
            'NV'=>"Nevada",
            'NH'=>"New Hampshire",
            'NJ'=>"New Jersey",
            'NM'=>"New Mexico",
            'NY'=>"New York",
            'NC'=>"North Carolina",
            'ND'=>"North Dakota",
            'OH'=>"Ohio",  
            'OK'=>"Oklahoma",  
            'OR'=>"Oregon",  
            'PA'=>"Pennsylvania",  
            'RI'=>"Rhode Island",  
            'SC'=>"South Carolina",  
            'SD'=>"South Dakota",
            'TN'=>"Tennessee",  
            'TX'=>"Texas",  
            'UT'=>"Utah",  
            'VT'=>"Vermont",  
            'VA'=>"Virginia",  
            'WA'=>"Washington",  
            'WV'=>"West Virginia",  
            'WI'=>"Wisconsin",  
            'WY'=>"Wyoming"
        );
        
        return $rStates;
    }

    static public function createUSStateOptionsHtml($sCurrentPostalCode = '')
    {
        $rStates = self::getUSStateArray();
        
        $sStateOptionsHtml = '';
        foreach ($rStates as $sPostalCode => $sStateName)
        {
            $sSelected = '';
            if ($sPostalCode == $sCurrentPostalCode)
            {
                $sSelected = 'selected';
            }
            $sStateOptionsHtml .= "<option value=\"$sPostalCode\" $sSelected>$sStateName</option>\n";
        }

        return $sStateOptionsHtml;
	}
	
	// From php.net (http://us2.php.net/manual/en/function.file-exists.php):
	// (renamed: was realPath)
	// (Also note that as of PHP 5 there is a parameter to file_get_contents that causes it to search the include path)
	/**
	 * Check if a file exists in the include path
	 * And if it does, return the absolute path.
	 * @param string $filename
	 *  Name of the file to look for
	 * @return string|false
	 *  The absolute path if file exists, false if it does not
	 */
	static public function file_exists_in_path ($filename)
	{
		// Check for absolute path
		if (realpath($filename) == $filename) {
			return $filename;
		}
		
		// Otherwise, treat as relative path
		$paths = explode(PATH_SEPARATOR, get_include_path());
		foreach ($paths as $path) {
			if (substr($path, -1) == DIRECTORY_SEPARATOR) {
				$fullpath = $path.$filename;
			} else {
				$fullpath = $path.DIRECTORY_SEPARATOR.$filename;
			}
			if (file_exists($fullpath)) {
				return $fullpath;
			}
		}
		
		return false;
	}
	
}
/**
 * I would like a more useful var_dump to live here.
 * 
 * It should be like the showDebugInfo() at WSOD in that it would appear as a closed div at the top of the page,
 * until you click on it and it expands.  That would be coooooool.
 */

/**
 * Need a utility function to do what ucwords() should be doing - allowing the user to
 * selectively capitalize words in a string, leaving alone words like
 * 'the', 'in', 'of' and 'and' if they are not the first word.
 * 
 * Maybe have a 'commonsense' parameter to turn this on? or call it 'selective'?
 * Maybe provide an array of words to leave alone - if this array is not provided, 
 * the selectivity will default to the above list
 */
?>
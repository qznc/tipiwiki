<?php
/* Original Author: Andreas Zwinkau <andi@buxach.de> - 14/6/2003
 * Description: A small, simple, standard-compliant wiki-system
 * Licence: GPL
 *
 * Modified by: Jose Sanchez <jose@serhost.com> 
*/

// Configuration, change these to your own needs
$data_dir = "data"; #$data_dir = "../../data"; to put the files out of the webservers area
$page_default = "FrontPage";
$template_show = "show.html";
$template_edit = "edit.html";
$wiki_get = "wiki";
// if you have to adjust the timezone (if server uses GMT and you want GMT+1, then $timezone must be 1
$timezone = 0;


// ++++++++ NO CHANGES BELOW HERE ! ++++++++
//       (unless you are a developer)

$page_delimiter = "### next entry ###\n";
$rev_get = "rev";


/*
// if ( PHP version < 4.1 ) $_GET is not available!
$version_info = explode('.', phpversion());
if ($version_info[0] < 4 || ($version_info[0] > 3 && $version_info[1] < 1)) {
        $_POST = $HTTP_POST_VARS;
        $_GET = $HTTP_GET_VARS;
}
// Does not seem to work with PHP5 ?
*/

// some specials
function findpage() {
	global $wiki_get;
	global $name;
	global $data_dir;
	$name = basename($name);
	$content = "";
	$formular = "</p><form method=\"get\" action=\"index.php\"><input type=\"hidden\" name=\"$wiki_get\" value=\"$name\" /><table id=\"find\"><tr><td>Look for page name</td><td><input type=\"text\" name=\"PageName\" /></td></tr><tr><td>or page content</td><td><input type=\"text\" name=\"PageContent\" /></td></tr><tr><td colspan=\"2\"><input type=\"submit\" value=\"search\" /></td></tr></table></form><p>\n";
	// get all existant pages from the directory
	$handle = opendir("$data_dir");
	while( $newdir = readdir($handle) )
		if( ($newfile != '.') and ($newfile != '..') )
			$allpages[] = $newdir;
	// look for matching page names
	if( isset($_GET["PageName"]) && $_GET["PageName"] != "" ) {
		$pagename = $_GET["PageName"];
		$content .= "</p><h3>Looking for page name $pagename</h3><ul>";
		foreach ($allpages as $page)
			if (preg_match("/$pagename/i",$page) )
				$content .= "<li><a href=\"index.php?$wiki_get=$page\">$page</a></li>";
			$content .= "</ul><p>\n";
	}
	// Look for matching page content
	if ( isset($_GET["PageContent"]) && $_GET["PageContent"] != "" ) {
		$pagecontent = $_GET["PageContent"];
		$content .= "</p><h3>Searching page content for $pagecontent</h3><ul>";
		foreach ($allpages as $page) {
			$current = implode( "", file("$data_dir/$page") );
			if (preg_match("/$pagecontent/i",$current) )
				$content .= "<li><a href=\"index.php?$wiki_get=$page\">$page</a></li>";
		}
		$content .= "</ul><p>\n";
	}
	if( $content == "" )
		$content = $formular;
	return $content;
}

function recentchanges() {
	global $data_dir;
	global $timezone;
	global $wiki_get;
	$content = "</p>";
	$handle = opendir($data_dir);
	$allpages = array();
	while( $newfile = readdir($handle) )
		if( ($newfile != '.') and ($newfile != '..') )
			$allpages[] = $newfile;
	$max_pages = sizeof($allpages) / 3;
	if( $max_pages < 30 )
		$max_pages = 30;
	$counter = 0;
	$date = mktime(12,0,0,date('m'),date('d'));
	$day = 0;
	while( $counter < $max_pages && $day < 40 ) {
		$today = array();
		foreach( $allpages as $page ) {
			$filetime = filemtime("$data_dir/$page");
			if( (($filetime + 43199) > $date) && (($filetime - 43200) < $date) )
				$today[] = $page;
		}
		if( sizeof($today) > 0 ) {
			$content .= "<h3>".date("d.m.Y",$date)."</h3><ul>";
			foreach( $today as $page )
				$content .= "<li><a href=\"index.php?$wiki_get=$page\">$page</a> (".date("G:i", filemtime("$data_dir/$page")+(3600*$timezone) ).")</li>";
			$content .= "</ul>";
		}
		$counter += sizeof($today);
		$date -= 86400;
		$day += 1;
	}
	$content .= "<p>\n";
	return $content;
}

function allpages() {
	global $data_dir;
	global $wiki_get;
	// list all existant pages
	$content = "</p><ul>";
	$handle = opendir($data_dir);
	while( $newfile = readdir($handle) )
		if( ($newfile != '.') and ($newfile != '..') )
			$allpages[] = $newfile;
	sort($allpages);
	foreach( $allpages as $page )
		$content .= "<li><a href=\"index.php?$wiki_get=$page\">$page</a></li>";
	$content .= "</ul><p>\n";
	return $content;
}

// format the WikiStyle to HTML
function filter($raw) {
	global $wiki_get;
	// from Simon Schoar <simon@schoar.de> :
	$regexURL = "((http|https|ftp|mailto):\/\/[A-Za-z0-9\.\:\@\?\&\~\%\=\+\-\/\_\;\#]+)";
	
	$filtered = stripslashes(str_replace("<","&lt;",str_replace(">","&gt;","\n".$raw)));

	// php-specific
	$filtered = str_replace("\r\n","\n",$filtered);

	// pictures [url]
	$filtered = preg_replace("/\[($regexURL\.(png|gif|jpg)) ?([^]]+)?\]/i","<img src=\"\\1\" class=\"wikiimage\" alt=\"\\5\"/>",$filtered);
    
	// [url link] external links
	$filtered = preg_replace("/\[$regexURL ([^]]+)\]/i","<a href=\"\\1\">\\3</a>", $filtered);
	
	// plain urls in the text
	$filtered = preg_replace("/(?<![\"\[])$regexURL(?!\")/","<a href=\"\\0\">\\0</a>",$filtered);

	// the WikiWords
	// look severak lines below for another way of creating wiki pages
    // this is set up for German umlaute
    setlocale (LC_ALL, 'de_DE');
	$filtered = preg_replace("/(?<=\s)([A-Z][a-z0-9äöüß\&]+){2,}/","<a href=\"index.php?$wiki_get=\\0\">\\0</a>", $filtered);
	
    // quotes
	$filtered = preg_replace("/\"(.+)\" -+ ([^\n]+)\n/U","<blockquote><q>\\1</q><sub>\\2</sub></blockquote>", $filtered);
	$filtered = preg_replace("/(?<=\s)\"(.+)\"/U","<q>\\1</q>", $filtered);

    // acronyms
	$filtered = preg_replace("/([A-Z]+) ?\((.+)\)/U","<acronym title=\"\\2\">\\1</acronym>", $filtered);

	// horizontal lines
	$filtered = preg_replace("/\n---.*\n/","<hr class=\"wiki\"/>",$filtered);

	// lists <ul>
	$filtered = preg_replace("/(?<=\n)\* (.+)\n/","<uli>\\1</uli>",$filtered);
	$filtered = preg_replace("/<uli>(.+)\<\/uli>/","</p><ul>\\0</ul><p>\n",$filtered);
	// lists <ol>
	$filtered = preg_replace("/(?<=\n)# (.+)\n/","<oli>\\1</oli>",$filtered);
	$filtered = preg_replace("/<oli>(.+)\<\/oli>/","</p><ol>\\0</ol><p>\n",$filtered);
    // ... cleaning lists
	$filtered = str_replace("<oli>","<li>",$filtered);
	$filtered = str_replace("</oli>","</li>",$filtered);
	$filtered = str_replace("<uli>","<li>",$filtered);
	$filtered = str_replace("</uli>","</li>",$filtered);
    
	// text decorations 
	$filtered = preg_replace("/\*\*([^\n]+)\*\*/U","<strong>\\1</strong>", $filtered);
	$filtered = preg_replace("/\*([^\n]+)\*/U","<em>\\1</em>", $filtered);
	$filtered = preg_replace("/_\((.+)\)/U","<sub>\\1</sub>", $filtered);
	$filtered = preg_replace("/\^\((.+)\)/U","<sup>\\1</sup>", $filtered);
	$filtered = preg_replace("/\[\[(.+)\]\]/","<span class=\"sidenote\">\\1</span>",$filtered); 

	// Headlines <h1><h2><h3>
	$filtered = preg_replace("/\n(===)(.+)\n/","</p><h4>\\2</h4><p>\n",$filtered);
	$filtered = preg_replace("/\n(==)(.+)\n/","</p><h3>\\2</h3><p>\n",$filtered);
	$filtered = preg_replace("/\n(=)(.+)\n/","</p><h2>\\2</h2><p>\n",$filtered);

	
	// strip leading and ending line breaks
	$filtered = preg_replace("/^(\n+)/","",$filtered); 
	$filtered = preg_replace("/\n{3,}/","\n",$filtered); 

	// <pre> blocks
	$filtered = preg_replace("/(?<=\n) (.*)(\n)/","<pre>\\1</pre>", $filtered);
	
	// create wiki pages with [brackets]
	// if you uncomment this line, you should comment the standard WikiWord line
	// this line makes words between such [ ] brackets a link to a wiki page
	$filtered = preg_replace("/\[([\w]+)\]/","<a href=\"index.php?$wiki_get=\\1\">\\1</a>", $filtered);
	
	// insert specials, check it first to prevent useless execution of the functions
	if( strpos($filtered, "&lt;findpage&gt;") !== FALSE )
		$filtered = str_replace("&lt;findpage&gt;", findpage(), $filtered);
	if( strpos($filtered, "&lt;allpages&gt;") !== FALSE )
		$filtered = str_replace("&lt;allpages&gt;", allpages(), $filtered);
	if( strpos($filtered, "&lt;recentchanges&gt;") !== FALSE )
		$filtered = str_replace("&lt;recentchanges&gt;", recentchanges(), $filtered);

    // smileys and other unicode
	$filtered = str_replace(":)", "☺", $filtered);
	$filtered = str_replace(":-)", "☺", $filtered);
	$filtered = str_replace(":(", "☹", $filtered);
	$filtered = str_replace(":-(", "☹", $filtered);
	$filtered = str_replace("=&gt;", "⇒", $filtered);
	$filtered = str_replace("&lt;=", "⇐", $filtered);
	$filtered = str_replace("-&gt;", "→", $filtered);
	$filtered = str_replace("&lt;-", "←", $filtered);

	// html beauty
	$filtered = str_replace("\n\n","</p><p>",$filtered);
	$filtered = str_replace("\n</p>","</p>",$filtered);
	$filtered = str_replace("<p>\n","<p>",$filtered);
	$filtered = str_replace("<p></p>","",$filtered);
	$filtered = str_replace("\n","<br />",$filtered);
	
	return $filtered;
}

// the ONLY page output of this script!
function output($data, $file) {
	$pagename = basename($file);
	$modified = "";
	if( file_exists($file) ) {
		$modified = date("H:i:s F d Y",filemtime($file));
	}
	$data = str_replace("<!--wikiname-->",$pagename,$data);
	$data = str_replace("<!--lastmodified-->",$modified,$data);
	echo $data;
}

// load a page
function get_page($file) {
    global $page_delimiter;
    global $_GET;
    $raw = "";
	$varaux=false; //To remove the IP and timestamp of the version
    foreach (file($file) as $line) {
		if (!$varaux) {
	   	    if ($line == $page_delimiter) {
    	   	    $raw = "";
				$varaux=true;
				continue;
	        } else {
   		        $raw .= $line;
	        }
		}
		$varaux=false;
    }
    return $raw;
}

function get_ver($file, $ver) {
    global $page_delimiter;
    global $_GET;
    $raw = "";
	$varaux=false; //To remove the IP and timestamp of the version
	$versioncounter=-1;
    foreach (file($file) as $line) {
		if (!$varaux) {
	   	    if ($line == $page_delimiter) {
				$versioncounter++;
				if ($versioncounter >= $ver)
					break;
    	   	    $raw = "";
				$varaux=true;
				continue;
	        } else {
   		        $raw .= $line;
	        }
		}
		$varaux=false;
    }
    return $raw;
}

function historial($file, $name) {
    global $page_delimiter;
    global $_GET;
    $raw = "";
	$theindex=1;
	$varaux=false; //Removes log line

    foreach (file($file) as $line) {
		if (!$varaux) {
	   	    if ($line == $page_delimiter) {
    	   	    $raw = "";
				$varaux=true;
				continue;
	        } else {
   		        $raw .= $line;
	        }
		}
		if ($varaux) {
			$varaux=false;
			$pieces=explode(" ", $line);
			$nick = $pieces[0];
			$thedate=date('d-m-Y H:i:s', $pieces[1]);
			$lines="<br/>\n<b><a href=\"?wiki=$name&amp;ver=$theindex\" />$thedate</a></b> (<a href=\"?wiki=$name&amp;ver=$theindex&edit=True\" />edit</a>) - $nick$lines";
			$theindex++;
		}
    }
	$lineas="<b>History of $name</b><br/>\r\n$lines";

	return $lineas;
}

// get the page content
function showpage($file, $ver) {
	global $template_show;
	global $wiki_get;
	$content = "";
	
	// get the wanted file
	if ($ver != "last")
		$raw=get_ver($file, $ver);
	else
	    $raw = get_page($file);

	// but filter it !
	$content = filter( $raw ) . $content;
	
	// get the page template
	$template = implode( "", file($template_show) );
	$whole = str_replace("<!--wikicontent-->",$content,$template);
	output( $whole, $file );
}

// if we have to edit a page
function editpage($file, $ver) {
	global $template_edit;
	$already = "";
	if( file_exists( $file ) ){
		if ($ver != "last")
			$already = get_ver($file, $ver);
		else
			$already = get_page($file);
	}

	if( file_exists( $template_edit ) )
		$template = file($template_edit);
	else
		echo "No Template (".$template_edit.") found!<br />\n";
	$template = implode( "", $template );
	$whole = str_replace("<!--already-->",stripslashes($already),$template);
	// file could be locked
	if( file_exists($file) && !is_writeable($file) )
		// note the s modifier !
		$whole = preg_replace("/<form .*<\/form>/s","<h3>Sorry, Page is locked (not writeable)</h3>", $whole);
	output($whole, $file );
}

// save the page to a file
function savepage($raw) {
	umask(0113);
	global $name;
    global $page_delimiter;
	$fname = $name;
	// now write the data into the page file
	$handle = fopen("$fname",'a');
    fwrite($handle, $page_delimiter);
	$temporalmark=time();
	fwrite($handle, getenv('REMOTE_ADDR'). " $temporalmark\n");
	if( ! fwrite($handle, $raw ) ) {
		$data_perm = decoct(fileperms($fname)) % 1000;
		$data_owner = (fileowner($fname));
		$data_group = (filegroup($fname));
		die("Error writing Data into $fname ( it has permissions $data_perm  and is owned by $data_owner:$data_group) !");
	}
	fclose($handle);
}

if (file_exists($name))
	header ("Last-Modified: " . filemtime($name));
else {
	//They are creating me
	$creating=True;
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
}

$edit = False;
$name = "$data_dir/$page_default";
if( isset( $_GET[$wiki_get] ) )
	$name = "$data_dir/".basename($_GET[$wiki_get]);
	# basename() solves security issue: $wiki_get could be "../../../../etc/passwd" ;)
	
// Assertation: Now we know the name of the current page

// write possible stuff from a edit session
if( isset($_POST["content"]) ) {
	$data = rtrim($_POST["content"])."\n";
	savepage($data);
}

if ($_REQUEST['historial']=="true"){
	echo historial($name, $_GET[$wiki_get]);
} else {

	if( ! file_exists( $name ) )
		$edit = True;
	if( isset($_GET["edit"]) && $_GET["edit"] == "True" )
		$edit = True;

	header ("Last-Modified: " . @filemtime($name));
	header("Content-Type: text/html;charset=utf-8");

	// shall we edit or just show it?
	if( $edit )
		if ($_GET['ver'] != "") {
			echo "You are editing an old version<br/>";
			editpage( $name, addslashes(htmlentities($_GET['ver'])) );
		} else {
			editpage( $name, "last" );
		}
	else
		if ($_GET['ver'] != "") {
			echo "You are seing an old version<br/>";
			showpage( $name, addslashes(htmlentities($_GET['ver'])));
		} else {
			showpage( $name, "last" );
		}
}
?>

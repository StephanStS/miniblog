<?php

/**
 * Front-end of Miniblog_XH.
 *
 * (c) 2016 svasti <http://svasti.de>
 * last edit 20.08.2016 11:49:04
 */

/**
 * Prevent direct access.
 */
if (!defined('CMSIMPLE_XH_VERSION')) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

define('MINIBLOG_VERSION','0.5.1');
if ((!isset($plugin_cf['miniblog']['version'])
    || $plugin_cf['miniblog']['version'] != MINIBLOG_VERSION)) {
    include_once $pth['folder']['plugins'] . 'miniblog/config/defaultconfig.php';
}


if (!(XH_ADM && $edit)
    && $plugin_tx['miniblog']['page-posts_backlinks_to']
    && $plugin_cf['miniblog']['posts_from_pages'])
{
    Miniblog_makeBackLink();
}

if ($plugin_cf['miniblog']['posts_from_members']) include_once 'writer.php';


/**
 * Creates a back link for page based posts
 * on all sub- and sub-sub-pages of the blog start page
 */
function Miniblog_makeBackLink()
{
    global $c, $h, $u, $pth, $plugin_cf, $plugin_tx;
    $blogpages = array();

    $bcklinkto = array_search($plugin_tx['miniblog']['page-posts_backlinks_to'], $h);
    $bcklinkfrom = $plugin_tx['miniblog']['page-posts_backlinks_from']
        ? array_search($plugin_tx['miniblog']['page-posts_backlinks_from'], $h)
        : $bcklinkto;

    if ($bcklinkfrom !== false) {

        foreach (Miniblog_childPages($bcklinkfrom) as $value) {
            if (!Miniblog_childPages($value)) {
                $blogpages[] = $value;
            } else {
                foreach (Miniblog_childPages($value) as $subvalue) {
                    $blogpages[] =  $subvalue;
                }
            }
        }
        if ($plugin_cf['miniblog']['backlinks_also_below_content']) {
            foreach ($blogpages as $k) {
                	$c[$k] = Miniblog_backLinkButton($u[$bcklinkto]) . $c[$k]
                           . Miniblog_backLinkButton($u[$bcklinkto], true);
                }
        } else {
            foreach ($blogpages as $k) {
            	$c[$k] = Miniblog_backLinkButton($u[$bcklinkto]) . $c[$k];
            }
        }
    }
}

/**
 * Creates a Backlink for single file posts
 */
function Miniblog_backLink($page, $bottom = null)
{
    global $h, $u;

    $bcklinkto = array_search($page, $h);
    return Miniblog_backLinkButton($u[$bcklinkto], $bottom);
}

/**
 * Makes the back link button
 */
function Miniblog_backLinkButton($link = '', $bottom = NULL)
{
    global $plugin_tx, $plugin_cf;

    if (!$link) return false;

    $o = "\n" . '<form method="post" class="mblog_backlink';
    if ($bottom) $o .= 'Bottom';
    $o .= '" action="?'
        . $link . '"><input type="submit" value="'
        . $plugin_tx['miniblog']['text_backlink']
        . '"></form>';

    return $o;
}



/**
 * Returns Array of pagenumbers of subpages of a page
 */
function Miniblog_childPages($n = NULL )
{
    global $s , $cl, $l, $cf;
    $n = is_numeric($n) ? $n : $s;

    $res = array();
    $ll = $cf['menu']['levelcatch'];
    for ($i = $n + 1; $i < $cl; $i++) {
        if ($l[$i] <= $l[$n]) {
            break;
        }
        if ($l[$i] <= $ll) {
            $res[] = $i;
            $ll = $l[$i];
        }
    }
    return $res;
}



/**
 * Adds data of blog pages to existing array of such data.
 */
function Miniblog_blogData($mblog, $page, $cat = '', $catpagenr = '', $style = '')
{
    global $plugin_cf, $plugin_tx, $h, $c, $pd_router;

    // make long plugin config var names a little shorter
    $pcf = $plugin_cf['miniblog'];

    $page_data = $pd_router->find_page($page);

    if (isset($page_data['expires']) && $page_data['expires']) {
        if (strtotime($page_data['expires']) < time()) return $mblog;
    }
    if (isset($page_data['publication_date']) && $page_data['publication_date']) {
        if (strtotime($page_data['publication_date']) > time()) {
            return $mblog;
        } else $mblog['date'][] = $date = strtotime($page_data['publication_date']);
    } else     $mblog['date'][] = $date = $page_data['last_edit'];

    $mblog['archive'][] = $pcf['archive_archiving_date']
                        && $date < strtotime($pcf['archive_archiving_date'])
                        && !$plugin_tx['miniblog']['single-file_archive_page']
                        ? true
                        : false;

    $mblog['cat'][]    = $cat;

    if ($catpagenr && $x = strpos($c[$catpagenr],'<img')) {
        $temp = substr($c[$catpagenr], $x);
        $mblog['img'][] = substr($temp, 0, strpos($temp,'>')+1);
    } else $mblog['img'][] = '';

    $mblog['pagenr'][] = $page;
    $mblog['ext'][]    = '';
    $mblog['style'][]  = $style;
    $mblog['title'][]  = $page_data['show_heading'] && $page_data['heading']
        ? $page_data['heading']
        : $h[$page];


    if ($pcf['keyword_show_keyword']) {
        if (isset($page_data['keywords']) && $page_data['keywords']) {
            $mblog['keyword'][] = $page_data['keywords'];
        } elseif ($pcf['keyword_2nd_source']) {
            preg_match ('!'.$pcf['keyword_2nd_source'].'.*>(.*)</!iU',$c[$page],$matches);
            $mblog['keyword'][] = isset($matches[1])? strip_tags($matches[1]):'';
        } else $mblog['keyword'][] = '';
    }
    $mblog['teaser'][] = isset($page_data['description']) && $page_data['description']
        ? $page_data['description']
        : preg_replace(array(
            '!\{\{\{.*($|\}\}\})!uU',
            '!#CMSimple.*#!U'),
            '', utf8_substr(strip_tags(substr($c[$page],
            (strpos($c[$page],$pcf['teaser_2nd_source'])),550)),0,
            $pcf['teaser_generated_length']));

    return $mblog;
}



/**
 * Main function, returns the table of contents with teasers for the blog posts.
 */
function miniblog($page = '', $sort = '', $showcatbuttons = '', $showarchivelink = '',
    $postsperpage = '', $columns = '', $showcat = '', $showdate = '', $showkeyword = '', $style = '')
{
    global $plugin_cf, $plugin_tx, $h, $c, $pd_router, $bjs, $s, $pth, $u, $sn, $sl;

    if ($s < 0) return;

    $o = '';
    $mblog = $catlist = $oldblog = array();
    $pcf = $plugin_cf['miniblog'];
    $ptx = $plugin_tx['miniblog'];

    $sort = $sort !== ''? $sort : $pcf['blogpage_order_posts'];
    $columns = $columns !== ''? $columns : $pcf['blogpage_2_columns'];
    $showcatbuttons = $showcatbuttons !== ''? $showcatbuttons : $pcf['category_category_selection_buttons'];
    $showarchivelink = $showarchivelink !== ''? $showarchivelink : $pcf['archive_show_archive_button'];
    $postsperpage = $postsperpage !== ''? $postsperpage : $pcf['blogpage_limit_nr_of_posts_per_page'];
    $style = $style !== ''? $style : $pcf['teaser_img_position'];

    // change config settings via plugin calling arguments
    if ($page) {
        $page = array_search($page, $h);
        $page = $page !== false ? $page : '';
    }

    // create sortable array for posts from cmsimple pages
    if ($pcf['posts_from_pages']) {
        if ($pcf['category_from_pagenames_with']) {
        // 1st case: category from pages with key words

            $cat = $catpage = $catselect = '';
            foreach (Miniblog_childPages($page) as $value) {
                // space is masked by "__" because otherwise categories 
                // containing a space would be treated as 2 categories
                if (strpos($h[$value],$pcf['category_from_pagenames_with']) === 0) {
                    $cat = str_replace(array(
                        $pcf['category_from_pagenames_with'],
                        ' '),
                        array('','__'), $h[$value]);
                    $catpage = $value;
                    if ($pcf['category_category_selection_buttons']) {
                        $catselect .= '<button class="mblog_off" id="'
                           . uenc($cat)
                           . '" type="submit" onclick="toggle_visibility(\''
                           . uenc($cat)
                           . '\');">'
                           . str_replace('__', ' ', $cat)
                           . '</button>';
                        $catlist[] = $cat;
                    }
                } else {
                    $mblog =  Miniblog_blogData($mblog, $value, $cat, $catpage, $style);
                }
            }
        } else {
        // 2nd case: category from subpages and content from subsubpages

            foreach (Miniblog_childPages($page) as $value) {

                if (!Miniblog_childPages($value)) {
                // if there are no subsubpages the content of the subpages is used as categoryless content
                    $mblog =  Miniblog_blogData($mblog, $value);
                } else {
                // standard case subsubpages as content
                    foreach (Miniblog_childPages($value) as $subvalue) {
                        $mblog =  Miniblog_blogData($mblog, $subvalue, $h[$value], $value, $style);
                    }
                }
            }
        }
    }

    // create sortable array for single file posts
    if ($pcf['posts_from_single_files']) {
        $posts = json_decode(file_get_contents($pth['folder']['content'].'miniblog/miniblog.php'), true);
        if ($posts) {
            foreach ($posts as $folder => $postings) {
                if ($folder == 'folder') continue;
                foreach ($postings as $post => $postdata) {

                    if (isset($postdata['publish']) && $postdata['publish'] && $postdata['publish'] != 'false'
                    && ((!isset($postdata['blog']) || $postdata['blog'] == $h[$s])
                    || !(strpos($ptx['single-file_backlinks_to'],',') || $ptx['single-file_archive_page']))
                    )
                    {
                        if ($pcf['archive_archiving_date'] && isset($postdata['archive']) && $postdata['archive'] == true) {
                            $mblog['archive'][]  = true;
                            $mblog['teaser'][]   = '';
                            $mblog['img'][]      = '';
                            $mblog['style'][]    = '';
                        } else {
                            $mblog['archive'][]  = false;
                            $mblog['teaser'][]   = isset($postdata['teaser'])  ? nl2br($postdata['teaser'])  :'';
                            $mblog['img'][]      = isset($postdata['img']) && $postdata['img']
                                ? '<img src="' . $pth['folder']['images']
                                . $postdata['img'] . '" alt="' . substr(basename($postdata['img']), 0, -4) . '">'
                                :'';
                            $mblog['style'][]= isset($postdata['style'])   ? $style               :'';
                        }
                        $mblog['date'][]     = isset($postdata['date'])    ? $postdata['date']    :'';
                        $mblog['cat'][]      = isset($postdata['cat'])     ? $postdata['cat']     :'';
                        $mblog['keyword'][]  = isset($postdata['name'])    ? $postdata['name']    :'';
                        $mblog['pagenr'][]   = '';
                        $mblog['ext'][]      = $ptx['single-file_blogname_in_url'] . '/' . $folder . '/' . $post;
                        $mblog['title'][]    = $postdata['title'];
                    }
                }
            }
        }
    }

    // sort posts
    if ($sort && $sort != '-') {
        if ($sort == 'title' || $sort == 'keyword' || $sort == 'category') {
            if ($sort == 'category') $sort = 'cat';
            array_multisort(array_map('uenc',$mblog[$sort]),
                    $mblog['title'],
                    $mblog['date'],
                    $mblog['cat'],
                    $mblog['keyword'],
                    $mblog['pagenr'],
                    $mblog['ext'],
                    $mblog['teaser'],
                    $mblog['archive'],
                    $mblog['style'],
                    $mblog['img']);
        } else {
            $sortdir = $sort == 'newest'? SORT_DESC : SORT_ASC;
            array_multisort($mblog['date'], $sortdir, SORT_NUMERIC,
                    $mblog['cat'],
                    $mblog['keyword'],
                    $mblog['pagenr'],
                    $mblog['ext'],
                    $mblog['title'],
                    $mblog['teaser'],
                    $mblog['archive'],
                    $mblog['style'],
                    $mblog['img']);
        }
    }

    // start Html
    $o .= "\n\n<!-- M I N I B L O G    S T A R T -->\n" . '<div class="mblog">' . "\n";

    // create buttons to toggle between different categories
    if ($showcatbuttons || $showarchivelink) {
        $buttonall = isset($ptx['single-file_archive_page'])
                     && $ptx['single-file_archive_page'] == $h[$s]
                   ? $ptx['text_archive']
                   : $ptx['text_no_selection'];
        $o .= '<div class="mblog_selectors">';

        // in case the blogpage has to be devided into multiple pages
        if ($postsperpage && count($mblog['title'])) {
            $k = ceil((count($mblog['title']) - count(array_filter($mblog['archive'])))
                / $postsperpage);
            for ($i = 1; $i <= $k; $i++) {
                $o .= $i == 1? '<button class="mblog_on"' : '<button class="mblog_off"';
                $o .= ' id="page'   . $i
                    . '" type="submit" onclick="toggle_visibility(\'page'
                    . $i
                    . '\');">';
                $o .= $i == 1?  $buttonall . ' 1' : $i;
                $o .= '</button>';
            }

        } else {
            // no subpages of the blogpage
            $o .=  '<button class="mblog_on" id="mblog_active" type="submit"
                    onclick="toggle_visibility(\'mblog_active\');">'
                . $buttonall . '</button>';
        }

        if ($pcf['posts_from_pages']) {
            if ($showcatbuttons && !$pcf['category_from_pagenames_with']) {
                foreach (Miniblog_childPages() as $cat) {
                    if (Miniblog_childPages($cat)) {
                        $o .= '<button class="mblog_off" id="'
                           . uenc($h[$cat])
                           . '" type="submit" onclick="toggle_visibility(\''
                           . uenc($h[$cat])
                           . '\');">'
                           . str_replace('__', ' ', $h[$cat])
                           . '</button>';
                        $catlist[] = $h[$cat];
                    }
                }
            } elseif ($showcatbuttons) $o .= $catselect;

        }
        if ($showcatbuttons && $pcf['posts_from_single_files'] && $ptx['single-file_category_list'])  {
            $cats = explode(',',$ptx['single-file_category_list']);
            $teasercats = $pcf['category_enable_multicategory']
                ? explode(' ', implode(' ', $mblog['cat']))
                : $mblog['cat']; 
            foreach ($cats as $cat) {
                $cat = str_replace(' ','__',trim($cat));
                if (in_array($cat, $teasercats) && !in_array($cat,$catlist)) {
                    $o .= '<button class="mblog_off" id="'
                       . uenc($cat)
                       . '" type="submit" onclick="toggle_visibility(\''
                       . uenc($cat)
                       . '\');">'
                       . str_replace('__', ' ', $cat)
                       . '</button>';
                }
            }
        }

        if ($showarchivelink) {
            if ($ptx['single-file_archive_page']) {
                $archivepage = array_search($ptx['single-file_archive_page'], $h);
                $o .= $archivepage === false || $archivepage == $s
                    ? ''
                    : '<form method="post" class="mblog_backlink" action="?'
                    . $u[$archivepage] . '"><input type="submit" value="'
                    . $ptx['text_archive']
                    . '"></form>';
            } else {
                $o .= '<button class="mblog_off" id="mblog_archive" type="submit"'
                    . ' onclick="toggle_visibility(\'mblog_archive\');">'
                    . $ptx['text_archive'] . '</button>';
            }
        }

        $bjs = '<script type="text/javascript">

    // <![CDATA[
    function getElementsByClassName(node,classname) {
        if (node.getElementsByClassName) { // use native implementation if available
            return node.getElementsByClassName(classname);
        } else {
            return (function getElementsByClass(node, classname) {
                var a = [];
                var re = new RegExp(\'(^| )\'+classname+\'( |$)\');
                var els = node.getElementsByTagName("*");
                for(var i=0,j=els.length; i<j; i++)
                    if (re.test(els[i].className))a.push(els[i]);
                return a;
             })(node, classname);
        }
    }

    function toggle_visibility(klas) {
        if (document.getElementById(klas).className == "mblog_off") {

            var allbuttons = getElementsByClassName(document, "mblog_on"),
                n = allbuttons.length;
            for (var i = 0; i < n; i++) {
                allbuttons[i].className = "mblog_off";
            }
            document.getElementById(klas).className = "mblog_on";

            var allitems = getElementsByClassName(document, "mblog_item"),
                n = allitems.length;
            for (var i = 0; i < n; i++) {
                allitems[i].style.display = klas == "mblog_active"
                    ? "inline-block"
                    : "none";
            }
            if (klas == "mblog_active") {
                var elements = getElementsByClassName(document, "mblog_archive"),
                    n = elements.length;
                for (var i = 0; i < n; i++) {
                    elements[i].style.display = "none";
                }
            }
            var elements = getElementsByClassName(document, klas),
                n = elements.length;
            for (var i = 0; i < n; i++) {
                elements[i].style.display = "inline-block";
            }
        } else {
            document.getElementById(klas).className = "mblog_off";
            document.getElementById("mblog_active").className = "mblog_on";

            var allitems = getElementsByClassName(document, "mblog_item"),
                n = allitems.length;
            for (var i = 0; i < n; i++) {
                allitems[i].style.display = "inline-block";
            }
            var elements = getElementsByClassName(document, "mblog_archive"),
                n = elements.length;
            for (var i = 0; i < n; i++) {
                elements[i].style.display = "none";
            }
        }
    }
    // ]]>
                  </script>' . "\n";
        $o .= '</div>' . "\n\n";
    }

    // create the items of the blog menu
    $page = $style = '';
    foreach ($mblog['date'] as $k=>$value) {
        if ($postsperpage) {
            $page =  ' page' . (ceil(($k + 1) / $postsperpage));
            $style = (ceil(($k + 1) / $postsperpage)) > 1
                  ? ' style="display:none;"'
                  : '';
        }
        $columnclass = $columns && strpos($mblog['style'][$k],'wide') !== 0
            ? 'mblog_columns '
            : '';
        $o .= $mblog['archive'][$k] && !$ptx['single-file_archive_page']
            ? '<div class="mblog_item mblog_archive" style="display:none">' . "\n"
            : '<div class="mblog_item ' . $columnclass
            . str_replace('xxxxx',' ',uenc(str_replace(' ', 'xxxxx', $mblog['cat'][$k])))
            . $page . '"' . $style . '>' . "\n";

       $id = $mblog['pagenr'][$k]
            ? uenc($h[$mblog['pagenr'][$k]])
            : str_replace(array($ptx['single-file_blogname_in_url'] . '/','/'),array('','---'),$mblog['ext'][$k]);

        $o .= Miniblog_header(
                $mblog['cat'][$k],
                $mblog['date'][$k],
                $mblog['keyword'][$k],
                $mblog['title'][$k],
                $mblog['img'][$k],
                $mblog['teaser'][$k],
                a($mblog['pagenr'][$k],$mblog['ext'][$k]),
                $mblog['archive'][$k],
                '',
                $mblog['style'][$k],
                $showcat,
                $showdate,
                $showkeyword,
                $id);

        $o .= "</div>\n\n";
    }

    $o .= "\n" . '</div>'."\n\n<!-- M I N I B L O G    E N D -->\n\n\n";

    return $o;
}


/**
 * Displays the header of a blog post
 */
function Miniblog_header($cat='', $date='', $keyword = '', $title = '', $img = '',
                         $teaser = '', $link = '', $archive = '', $admin = '', $style = '',
                         $showcat = '', $showdate = '', $showkeyword = '', $id = '')
{
    global $plugin_cf, $plugin_tx, $pth;
    $o = $catId = $dateId = $nameId = $titleId = '';
    $pcf = $plugin_cf['miniblog'];
    $ptx = $plugin_tx['miniblog'];

    $style = !$style ? $pcf['teaser_img_position'] : $style;


    $showcat = $showcat !== ''? $showcat : $pcf['category_show_category_in_teaser'];
    $showdate = $showdate !== ''? $showdate : $pcf['date_show_date'];
    $showkeyword = $showkeyword !== ''? $showkeyword : $pcf['keyword_show_keyword'];

    if ($admin) {
        if (!$cat) $cat = $ptx['error_category_missing'];
        $catId = 'id="mblog_cat"';
        $dateId = 'id="mblog_date"';
        $nameId = 'id="mblog_name"';
        $titleId = 'id="mblog_title"';
    }
    $date = $date? date($pcf['date_format'],(int)$date) : $ptx['error_date_missing'];
    if (!$title) $title = $ptx['error_title_missing'];

    $img = $archive ? '' : $link . $img . '</a>';


    $o .= $style == 'top' || $style == 'wide_top'
        ? $img
        : ($style == 'left'
        ? '<span class="mblog_imgleft">' . $img . '</span><span class="mblog_textright">'
        : '') ;

    $o .= $showcat && $style != 'left'
        ? "\n" . '<p class="mblog_cat" ' . $catId . '>' . str_replace('__', ' ', $cat) . '</p>'
        : '';
    $o .= '<p>';
    $o .= $showdate
        ? "\n" . '<span class="mblog_date" ' . $dateId . '>' . $date . '</span>'
        : '';
    $o .= $showkeyword
        ? "\n" . ' <span class="mblog_keyword" ' . $nameId . '>' . $keyword . '</span>'
        : '';
    if ($style == 'left' && $showcat) $o .= ' (<span class="mblog_cat" '
        . $catId . '>' . str_replace('__', ' ', $cat) . '</span>)';
    $o .= '</p>';

    $headstyle = $archive ? $pcf['archive_archive_headline_style'] : $pcf['teaser_headline_style'];
    $o .= "\n" . '<' . $headstyle . ' class="mblog_title" ' . $titleId . '>';
    $o .= $style == 'title' || $style == 'wide_title' ? $img : '' ;
    $o .= $link && $pcf['teaser_headline_is_link']
        ? $link . $title . '</a>'
        : $title;
    $o .= '</' . $headstyle . '>'
       . "\n";
    $o .= '<p class="mblog_teaser">'
        . ($style == 'teaser' || $style == 'wide_teaser' ? $img : '')
        . $teaser . ($admin ? '' : ' <span>'
        . $link . $ptx['text_more'] .'</a></span>') . '</p>';

    if ($pcf['comments_plugin'] && !$admin && $id) {
        $o .= Miniblog_commentCount($id);
    }
    if ($style == 'left') $o .= '</span>';
    return $o;
}



/**
 * Count the number of Comments for a post, works with twocents_XH + comments_XH
 */
function Miniblog_commentCount($id)
{
    global $pth, $plugin_cf, $plugin_tx;
    $o = $comments = '';

    if ($plugin_cf['miniblog']['comments_plugin'] == "comments") {
        $filetype = '.txt';
        $comments = true;
    } else $filetype = '.csv';

    if (is_file($pth['folder']['content'] . $plugin_cf['miniblog']['comments_plugin'] . '/' . $id . $filetype)) {
        $file = $pth['folder']['content'] . $plugin_cf['miniblog']['comments_plugin'] . '/' . $id . $filetype;
        $linecount = $comments ? -1 : 0;
        $handle = fopen($file, "r");
        while(!feof($handle)){
            $line = fgets($handle);
            if ($comments) $linecount++;
            elseif (is_numeric($line[0])) $linecount++;
        }
        fclose($handle);
        if($linecount > 0) {
            return '<p class="mblog_commentcount"><span>'
                . sprintf($plugin_tx['miniblog']['text_comments' . XH_numberSuffix($linecount)], $linecount)
                . '</span></p>';
        } 
    } 
}



/**
 * Make sure the next function will load after other plugins
 */
XH_afterPluginLoading(
    function () {
        global $s, $su, $o, $plugin_tx;

        if ($s == -1 && strpos($su, $plugin_tx['miniblog']['single-file_blogname_in_url'] . '/') === 0
            && strlen($su) >  strlen(($plugin_tx['miniblog']['single-file_blogname_in_url']) + 2)) {
            $o = Miniblog_extPage($su);
        }
    }
);



/**
 * Creates a temporary CMSimple_XH page from a single file blog post
 */
function Miniblog_extPage($url) {

    global $pth, $plugin_cf, $plugin_tx, $s, $tx, $su, $f, $title;
    $o = '';

    $posts = json_decode(file_get_contents($pth['folder']['content'].'miniblog/miniblog.php'), true);
    list($blog,$folder,$post) = explode('/',$url);

    $tx['meta']['description'] = $posts[$folder][$post]['name']
        . ' (' . $posts[$folder][$post]['cat'] . '): '
        . $posts[$folder][$post]['teaser'];
    $title = $posts[$folder][$post]['title'];

    $backlink = isset($posts[$folder][$post]['blog']) && $posts[$folder][$post]['blog']
        ? $posts[$folder][$post]['blog']
        : (!strpos($plugin_tx['miniblog']['single-file_backlinks_to'],',')
        ? $plugin_tx['miniblog']['single-file_backlinks_to']
        : substr($plugin_tx['miniblog']['single-file_backlinks_to'], 0, strpos($plugin_tx['miniblog']['single-file_backlinks_to'],',')));
    $o .= Miniblog_backLink($backlink);

    $o .= '<div class="mblog_postHeader">';

    $o .= Miniblog_header(
            $posts[$folder][$post]['cat'],
            $posts[$folder][$post]['date'],
            $posts[$folder][$post]['name'],
            $posts[$folder][$post]['title'],'','','','',true);

    $o .= '</div>';

    $t  = file_get_contents($pth['folder']['content'] . 'extedit/'  . $folder . '---' . $post . '.htm');
    if ($plugin_cf['miniblog']['comments_plugin'] && $posts[$folder][$post]['comments'] == 'true')
        $t .= '{{{' . $plugin_cf['miniblog']['comments_plugin'] . ' "' . $folder . '---' . $post . '"}}}';
    $o .= evaluate_scripting($t);

    if ($plugin_cf['miniblog']['backlinks_also_below_content']) {
        $o .= Miniblog_backLink($backlink, true);
    }
    return $o;
}



/**
 * Enlarges the standard search function to single file blog posts
 */
function Miniblog_search()
{
	global $pth, $u, $cl, $h, $l, $c, $f, $plugin_tx;

    $posts = json_decode(file_get_contents($pth['folder']['content'].'miniblog/miniblog.php'), true);
    foreach ($posts as $folder => $postings) {
        foreach ($postings as $post => $postdata) {
            if (isset($postdata['publish']) && $postdata['publish'] && $postdata['publish'] != 'false') {
                $h[] = $posts[$folder][$post]['title'];
                $u[] = $plugin_tx['miniblog']['single-file_blogname_in_url'] . '/' . $folder . '/' . $post;
                $c[] = file_get_contents($pth['folder']['content'] . 'extedit/'  . $folder . '---' . $post . '.htm');
            }
        }
    }
}

if ($f == 'search') Miniblog_search();



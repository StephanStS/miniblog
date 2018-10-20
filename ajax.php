<?php 
$bjs .= '<script type="text/javascript">
// ajax for blog head data
function mblogSendHeader () {
    window.onerror = function() {
        alert("' . $ptx['error_ajax'] . '");
        return true;
    };
    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (xhttp.readyState == 4 && xhttp.status == 200) {
            var return_data = xhttp.responseText;
            var answer = document.getElementById("mblogEditAnswer");
            answer.innerHTML = return_data;
            setTimeout(function(){answer.innerHTML = ""},4000);
            }
        }

    function mblogShowHeader (item1,item2,txt) {
        if  (typeof document.getElementById(item1) != "undefined"
            && typeof document.getElementsByName(item2)[0] != "undefined") {
            if (document.getElementsByName(item2)[0].value) {
                document.getElementById(item1).innerHTML =  document.getElementsByName(item2)[0].value;
            } else {
                document.getElementById(item1).innerHTML =  txt;
            }
        }
    }

    var publ = document.getElementsByName("mblogpublish")[0].checked;
    if (publ == true) {
        document.getElementById("mblogPublStatus").innerHTML = "' . $ptx['text_published'] . '";
        document.getElementById("mblogPublStatus").className = "mblogPublOn";
    }
    else if (publ == false) {
        document.getElementById("mblogPublStatus").innerHTML = "' . $ptx['text_not_published'] . '";
        document.getElementById("mblogPublStatus").className = "mblogPublOff";
    }
';

if($pcf['keyword_show_keyword']) {
    $bjs .= '
    //author s name or keyword
    mblogShowHeader ("mblog_name","mblogname","");';
}
if($pcf['date_show_date']) {
    $bjs .= '
    //date
    if  (typeof document.getElementById("mblog_date") != "undefined"
        && typeof document.getElementsByName("mblogdate")[0] !== "undefined") {
        document.getElementById("mblog_date").innerHTML =  document.getElementsByName("mblogdate")[0].value < 1
            ?  "' . $ptx['error_date_missing'] . '"
            :  document.getElementsByName("mblogdate")[0].value ;
    }';
}
$bjs .= '
    //title
    mblogShowHeader ("mblog_title","mblogtitle","' . $ptx['error_title_missing'] . '");

    //category
    var cat = "";
';
if ($ptx['single-file_category_list']) $bjs .= '
    var x = document.getElementById("mblogcat");
    for (var i = 0; i < x.options.length; i++) {
        if(x.options[i].selected == true) {
            if(cat) cat += " ";
            cat += x.options[i].value;
       }
    }
';
if ($pcf['category_show_category_in_teaser']) $bjs .= '
    if (!cat) {
        document.getElementById("mblog_cat").innerHTML = "' . $ptx['error_category_missing'] . '";
    } else document.getElementById("mblog_cat").innerHTML = cat.replace(/__/g," ");
';

$bjs .= '
    //send ajax
    xhttp.open("POST", "' . $sn . '?miniblog&mblogwriter_ajax", true);
    xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

    var vars = '. $nameajax  . '
    + "&mblogfolder="   + "'. $folder . '"
    + "&mblogpost="     + "'. $post  . '"
    + "&mblogblog="     + document.getElementsByName("mblogblog")[0].value
    + "&mblogdate="     + document.getElementsByName("mblogdate")[0].value
    + "&mblogtitle="    + document.getElementsByName("mblogtitle")[0].value
    + "&mblogcat="      + cat
    + "&mblogpublish="  + document.getElementsByName("mblogpublish")[0].checked
    + "&mblogcomments=" + document.getElementsByName("mblogcomments")[0].checked
    + "&mblogteaser="   + document.getElementsByName("mblogteaser")[0].value
    + "&mblogstyle="    + document.getElementsByName("mblogstyle")[0].value
    + "&mblogimg="      + document.getElementsByName("mblogimg")[0].value;
    xhttp.send(vars);
}
// autogrowing text area input field
function makeExpandingArea(container) {
    var area = container.querySelector("textarea");
    var span = container.querySelector("span");
    if (area.addEventListener) {
        area.addEventListener("input", function() {
            span.textContent = area.value;
        }, false);
        span.textContent = area.value;
    }
    container.className += " active";
}
var areas = document.querySelectorAll(".mblog_expandingArea");
var l = areas.length;
while (l--) {
 makeExpandingArea(areas[l]);
}</script>';

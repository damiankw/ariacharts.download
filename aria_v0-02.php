<?php
/* ariaget.php - Aria Chart Download Script
   By damian (damian@damian.id.au) 2010
   -

/*
  - database
  ----------
  CREATE TABLE ariachart (
    date INT(15),           // chart date
    this INT(2),
    last INT(2),
    high INT(2),
    title VARCHAR(80),
    artist VARCHAR(80),
    PRIMARY KEY(date, this)
  );

*/

define('CRLF', "\n\r");
define('ARIA_SINGLE', 'ARIA_SINGLE');
define('ARIA_ALBUM', 'ARIA_ALBUM');
define('ARIA_DANCE', 'ARIA_DANCE');
define('ARIA_COUNTRY', 'ARIA_COUNTRY');
define('ARIA_COMPILATION', 'ARIA_COMPILATION');


class ariachart {
  function curl($URL, $TYPE=NULL, $INFO=NULL) {
     // Initialize cURL:
    $curl = curl_init();

    // Set the URL to open:
    curl_setopt($curl, CURLOPT_URL, $URL);
    curl_setopt($curl, CURLOPT_USERAGENT, 'User-Agent: Mozilla/4.0 (compatible; MSIE 6.0; Windows NT)');
    curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    if ($TYPE == CURLOPT_HTTPAUTH) {
      curl_setopt($curl, CURLOPT_USERPWD, $INFO);
      curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
    } elseif ($TYPE == CURLOPT_COOKIE) {
      curl_setopt($curl, CURLOPT_COOKIE, $INFO);
    }

    // Execute run commands:
    $output = curl_exec($curl);

    // Close the connection:
    curl_close($curl);

    // Return output:
    return $output;
  }

  function getChart($TYPE='') {
    if ($TYPE == 'singles') {
      $feed = $this->curl("http://www.ariacharts.com.au/pages/charts_display_singles.asp?chart=1U50");
    } else {
      $feed = $this->curl("http://www.ariacharts.com.au/pages/charts_display_album.asp?chart=1G50");
    }

    $HTML = new DomDocument();
    if (@!$HTML->loadHTML($feed)) {
      return null;
    }

    foreach ($HTML->getElementsByTagName('table') as $TABLE) {
      if ($TABLE->getAttribute('class') == 'chartTable') {
        foreach ($TABLE->getElementsByTagName('tr') as $TR) {
          $cntTD = 1;
          foreach ($TR->getElementsByTagName('td') as $TD) {
            $TRACK[$this->tdName($cntTD)] = $TD->nodeValue;
            $cntTD++;
          }
          unset($TRACK['']);
          $CHART[$TRACK['this']] = $TRACK;
        }
      }
    }

    return $CHART;
  }

  function tdName($i) {
    if ($i == 2) {
      return 'this';
    } elseif ($i == 3) {
      return 'last';
    } elseif ($i == 4) {
      return 'times';
    } elseif ($i == 7) {
      return 'title';
    } elseif ($i == 8) {
      return 'artist';
    }

    return;
  }

  function newsgroup($SERVER, $USER, $PASS, $GROUP=NULL) {
    echo '<pre>';

    if ($GROUP == NULL) {
      /* This part will connect to news server and list ALL groups available:       */
      $SERVER = '{'. $SERVER . ':119/nntp}';
      $news = imap_open($SERVER, $USER, $PASS, OP_HALFOPEN);

      echo 'Listing all alt.binaries.hdtv.* ['. $SERVER .']'.CRLF;
      $group = imap_getmailboxes($news, $SERVER, "*php*");

      foreach ($group as $key => $value) {
        echo "($key): ". $value->name .CRLF;
      }
    } else {
      /* This will connect to news server and list posts from group:                */
      $SERVER = '{'. $SERVER . ':119/nntp}';
      $news = imap_open($SERVER . $GROUP, $USER, $PASS, OP_ANONYMOUS);

      echo 'Listing posts in '. $GROUP .' ['. $SERVER .'] LIMIT 100'. CRLF;
      $total = imap_num_msg($news);

      echo 'Total: '. $total . $CRLF;

      for ($i = $total-10; $i <= $total; $i++) {
        $post = imap_header($news, $i);
        //if (!$post->Size) { continue; }

        echo $post->subject .' ('. $post->from[0]->mailbox .'@'. $post->from[0]->host .')' .CRLF;
      }



//    $get = imap_getsubscribed($news, $SERVER, '/alt.binaries.hdtv.x264');

//    print_r($get);

//    $head = imap_thread($news);
//   $head = imap_headers($news);
//   print_r($head);

    }

    echo '</pre>';


/****************************************************************/
/*
// open a connection to the nntp server
$server = "{news.tpg.com.au/nntp:119}";
$group = "alt.alien.research";
$nntp = imap_open("$server$group", $USER, $PASS,OP_ANONYMOUS);


    // read and display posting index
$last = imap_num_msg($nntp);
echo $last;
$n = 10; // display last 10 messages

// table header
print <<<EOH
<table>
<tr>
    <th align="left">Subject</th>
    <th align="left">Sender</th>
    <th align="left">Date</th>
</tr>
EOH;

// the messages
for ($i = $last-$n+1; $i <= $last; $i++) {
    $header = imap_header($nntp, $i);

    if (! $header->Size) { continue; }

    $subj  = $header->subject;
    $from  = $header->from;
    $email = $from[0]->mailbox."@".$from[0]->host;
    $name  = $from[0]->personal ? $from[0]->personal : $email;
    $date  = date('m/d/Y h:i A', $header->udate);

print <<<EOM
<tr>
    <td><a href="$_SERVER[PHP_SELF]"?msg=$i\">$subj</a></td>
    <td><a href="mailto:$email">$name</a></td>
    <td>$date</td>
</tr>
EOM;
     }

// table footer
echo "</table>\n";
*/
  }




}

 $a = new ariachart();
// echo '<pre>';
// print_r($a->getChart('singles'));
// echo '<br />';
// print_r($a->getChart('albums'));
 //echo $a->curl("http://www.ariacharts.com.au/pages/charts_display_singles.asp?chart=1U50");

 //$a->newsgroup('news.tpg.com.au', 'damiankw', 'maddog', 'alt.alien.research');
?>
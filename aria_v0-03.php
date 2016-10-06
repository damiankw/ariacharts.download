<?php
/* ariaget.php - Aria Chart Download Script
   By damian (damian@damian.id.au) 2010
     With assistance by Orkon on NNTP/HTML Parsing.
   -

/*
  - database
  ----------
  CREATE TABLE category (
    cat_id INT(2) AUTO_INCREMENT,
    chart VARCHAR(10),
    name VARCHAR(20),
    PRIMARY KEY(cat_id)
  );

  CREATE TABLE charts (
    date INT(15),
    chart VARCHAR(20),
    this INT(2),
    last INT(2),
    title VARCHAR(80),
    artist VARCHAR(80),
    retrieved BOOLEAN DEFAULT FALSE,
    PRIMARY KEY(date, chart, this)
  );

INSERT INTO category (chart, name) VALUES('1U50', 'Singles');
INSERT INTO category (chart, name) VALUES('1G50', 'Albums');
INSERT INTO category (chart, name) VALUES('1U20AUS', 'Australian Singles');
INSERT INTO category (chart, name) VALUES('1A20AUS', 'Australian Albums');
INSERT INTO category (chart, name) VALUES('1D20', 'Dance Singles');
INSERT INTO category (chart, name) VALUES('1F20', 'Country Singles');
INSERT INTO category (chart, name) VALUES('1B20', 'Compilation Albums');
INSERT INTO category (chart, name) VALUES('1R40RB', 'Urban Singles');
INSERT INTO category (chart, name) VALUES('1Q40RB', 'Urban Albums');

*/

define('CRLF', "\n\r");

define('ARIA_SINGLE', '1U50');
define('ARIA_ALBUM', '1G50');
define('ARIA_AUS', '1U20AUS');
define('ARIA_AUSALBUM', '1A20AUS');
define('ARIA_DANCE', '1D20');
define('ARIA_COUNTRY', '1F20');
define('ARIA_COMPILATION', '1B20');
define('ARIA_URBAN', '1R40RB');
define('ARIA_URBANALBUM', '1Q40RB');


function align($TEXT, $OFFSET) {
  return $TEXT . str_repeat(' ', $OFFSET - strlen($TEXT));
}

class ariaget {
  var $LINK;

  var $SQL = Array(
    'hostname' => 'localhost',
    'username' => 'ariaget',
    'password' => 'ariaget123',
    'database' => 'ariaget',

    'tb_catinfo' => 'category', // A list of charts and their meanings.
    'tb_chartinfo' => 'charts', // All of the current titles.
  );

  function ariaget() {
    // Will store a chart to SQL
    $this->LINK = mysql_connect($this->SQL['hostname'], $this->SQL['username'], $this->SQL['password']);  // connect to db
    mysql_select_db($this->SQL['database']);
  }

  function curl($URL, $TYPE=NULL, $INFO=NULL) {
    /* curl(<url>, <CURLOPT_TYPE>, <extra information>) - Will grab a website using information specified. */

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

  function chart_get($GETCHART=ARIA_SINGLE) {
    /* chart_get(<type>) - Will retrieve the chart from the Ariacharts.com.au website and return the results in an Array() */

    // Retrieve the HTML from the website;
    $feed = $this->curl('http://www.ariacharts.com.au/pages/charts_display.asp?chart='. $GETCHART);

    // Create a new HTML Reader;
    $HTML = new DomDocument();
    if (@!$HTML->loadHTML($feed)) {
      return null;
    }

    // Grab the table from the HTML that is needed, store the table rows into an Array;
    foreach ($HTML->getElementsByTagName('table') as $TABLE) {
      if ($TABLE->getAttribute('class') == 'chartTable') {
        foreach ($TABLE->getElementsByTagName('tr') as $TR) {
          $cntTD = 1;
          foreach ($TR->getElementsByTagName('td') as $TD) {
            // Grab the track information;
            $TRACK[$this->td_name($cntTD)] = ($TD->nodeValue == '-' ? '0' : str_replace("'", "\'", $TD->nodeValue));
            $cntTD++;
          }

          // Insert track into Chart;
          $CHART[$TRACK['this']] = $TRACK;
        }
      }
    }

    // Return the chart as an Array();
    unset($CHART['']);
    return $CHART;
  }

  function show_new() {
    $q = mysql_query("SELECT DISTINCT date, artist, title, category.name FROM charts, category WHERE (last = 0) AND (charts.chart = category.chart) ORDER BY date, name, artist", $this->LINK);
    $c = 0;
    while ($d = mysql_fetch_array($q)) {
      if ($c != $d['date']) {
        echo '|----|--------------------|-------------------------------------------------------------------------------------------|<br />';
        echo '<br />';
        echo date('r', $d['date']) .'<br />';
        echo '|----|--------------------|-------------------------------------------------------------------------------------------|<br />';
        echo '| '. align('Wk', 2) .' | '. align('Chart', 18) .' | '. align('Artist - Title', 90) .'|<br />';
        echo '|----|--------------------|-------------------------------------------------------------------------------------------|<br />';
        $c = $d['date'];
      }
      echo '| '. align(date('W', $d['date']), 2) .' | '. align($d['name'], 18) .' | '. align(($d['artist'] .' - '. $d['title']), 90) .'|<br />';
    }

    echo '|----|--------------------|-------------------------------------------------------------------------------------------|<br />';
  }

  function chart_store() {
    $QUERY = mysql_query("SELECT chart, name FROM ". $this->SQL['tb_catinfo'], $this->LINK);

    if (mysql_num_rows($QUERY) == 0) {
      return false;
    }

    while ($DATA = mysql_fetch_array($QUERY)) {
      $CHART = $this->chart_get($DATA['chart']);

      foreach ($CHART as $KEY => $VALUE) {
        mysql_query("INSERT INTO ". $this->SQL['tb_chartinfo'] ." (date, chart, this, last, title, artist) VALUES(UNIX_TIMESTAMP(DATE(NOW())), '". $DATA['chart'] ."', '". $VALUE['this'] ."', '". $VALUE['last'] ."', '". $VALUE['title'] ."', '". $VALUE['artist'] ."')", $this->LINK);
      }

      echo date('r') .' - Recorded: '. $DATA['name'] .CRLF;
    }
  }

  function td_name($i) {
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
    if ($GROUP == NULL) {
      /* This part will connect to news server and list ALL groups available:       */
      $SERVER = '{'. $SERVER . ':119/nntp}';
      $news = imap_open($SERVER, $USER, $PASS, OP_HALFOPEN);

      echo 'Listing all alt.binaries.teevee.* ['. $SERVER .']'.CRLF;
      $group = imap_getmailboxes($news, $SERVER, "*x264*");

      foreach ($group as $key => $value) {
        echo "($key): ". $value->name .CRLF;
      }
    } else {
      /* This will connect to news server and list posts from group:                */
      echo '> Making a connection to the server ...';
      $SERVER = '{'. $SERVER . ':119/nntp}';
      $news = imap_open($SERVER . $GROUP, $USER, $PASS, OP_ANONYMOUS);
      echo 'done! ('. $news .')<br />'; flush();

      echo '> Collecting total number of messages ...';
      $total = imap_num_msg($news);
      echo 'done! ('. $total .')<br />'; flush();

//      echo 'Total: '. $total . $CRLF;

//      for ($i = $total-10; $i <= $total; $i++) {
//        $post = imap_header($news, $i);
//        if (!$post->Size) { continue; }
//
//        echo $post->subject .' ('. $post->from[0]->mailbox .'@'. $post->from[0]->host .')' .CRLF;
//      }
    }
  }
}

//  $a = new ariaget();
//  echo '<pre>';
// $a->chart_store();
// echo '<br />';
// print_r($a->chart_get(ARIA_AUSALBUM));
// echo $a->curl("http://www.ariacharts.com.au/pages/charts_display_singles.asp?chart=1U50");

//  echo '> Probing alt.php.sql:<br />'; flush();
//  $a->newsgroup('news.giganews.com', 'damian', '43DA0887', 'alt.php.sql');
//  flush();

//  echo '<br />';

//  echo '> Probing alt.binaries.movies.zeromovies ...<br />'; flush();
// $a->newsgroup('news.giganews.com', 'damian', '43DA0887', 'alt.binaries.movies.zeromovies'); flush();
//  echo 'done!<br />'; flush();

//  echo '</pre>';
?>
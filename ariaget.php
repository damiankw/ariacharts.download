<?php
/* ariaget.php - Aria Chart Download Script
   By damian (damian@damian.id.au) 2010
     With assistance by Orkon on NNTP/HTML Parsing.
   - Requires php-curl

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

function align($TEXT, $OFFSET) {
  return $TEXT . str_repeat(' ', $OFFSET - strlen($TEXT));
}

class ariaget {
  var $LINK;
  var $VERSION;

  var $SQL = Array(
    'hostname' => 'victor.local.nictitate.net',
    'username' => 'ariaget',
    'password' => 'ariaget123',
    'database' => 'ariaget',

    'tb_catinfo' => 'category', // A list of charts and their meanings.
    'tb_chartinfo' => 'charts', // All of the current titles.
  );

  function ariaget() {
    $this->VERSION = '1.00';
    // Will store a chart to SQL
    $this->LINK = mysqli_connect($this->SQL['hostname'], $this->SQL['username'], $this->SQL['password'], $this->SQL['database']);  // connect to db
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

  function chart_get($GETCHART='albums') {
    /* chart_get(<type>) - Will retrieve the chart from the Ariacharts.com.au website and return the results in an Array() */

    // Retrieve the HTML from the website;
    $feed = $this->curl('http://www.ariacharts.com.au/chart/'. $GETCHART);

    // Create a new HTML Reader;
    $HTML = new DomDocument();
    if (@!$HTML->loadHTML($feed)) {
      return null;
    }

    // Check through all of the DIV elements..
    foreach ($HTML->getElementsByTagName('div') as $ITEM) {
      // Check if the element is an item..
      if ((substr($ITEM->getAttribute('class'), 0, 4) == 'item')) {
        $TRACK = null;
        // Check through all of the DIV elements again..
        foreach ($ITEM->getElementsByTagName('div') as $LINE) {
          if ($LINE->getAttribute('class') == 'column col-1') {
            // If it's the first column - we have this week
            $TRACK['this'] = trim($LINE->nodeValue);
          } elseif ($LINE->getAttribute('class') == 'column col-2') {
            // If it's the second column - we have last week
            $TRACK['last'] = trim($LINE->nodeValue);
          } elseif ($LINE->getAttribute('class') == 'column col-6') {
            // If it's the sixth column - we have the title (have to clean it up)
            foreach ($LINE->getElementsByTagName('h3') as $TITLE) {
              $TRACK['title'] = explode(PHP_EOL, $TITLE->nodeValue);
              $TRACK['title'] = trim($TRACK['title'][0]);
            }

            // If it's the sixth column - we have the artist (have to clean it up)
            foreach ($LINE->getElementsByTagName('p') as $ARTIST) {
              $TRACK['artist'] = explode(PHP_EOL, $ARTIST->nodeValue);
              $TRACK['artist'] = trim($TRACK['artist'][0]);
            }
          }
        }

        // Add the title into the chart
        $CHART[$TRACK['this']] = $TRACK;
      }
    }

    // Return the chart
    return $CHART;
  }

  function show_new() {
    $q = $this->LINK->query("SELECT DISTINCT date, artist, title, category.name FROM charts, category WHERE (last = 0) AND (charts.cat_id = category.cat_id) ORDER BY date DESC, name, artist");
    $c = 0;
    while ($d = $q->fetch_array()) {
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
    // Gather all of the charts from the database
    $QUERY = $this->LINK->query("SELECT cat_id, chart, name FROM ". $this->SQL['tb_catinfo']);

    // If there aren't any, return an error
    if ($QUERY->num_rows == 0) {
      return false;
    }

    // Loop through the charts
    while ($DATA = $QUERY->fetch_array()) {
      // Download the chart
      $CHART = $this->chart_get($DATA['chart']);

      // Store each title in the database
      foreach ($CHART as $KEY => $VALUE) {
        $this->LINK->query("INSERT INTO ". $this->SQL['tb_chartinfo'] ." (date, cat_id, this, last, title, artist) VALUES(UNIX_TIMESTAMP(DATE(NOW())), '". $DATA['cat_id'] ."', '". $VALUE['this'] ."', '". $VALUE['last'] ."', '". $VALUE['title'] ."', '". $VALUE['artist'] ."')");
      }

      // echo out a little note
      echo date('r') .' - Recorded: '. $DATA['name'] .PHP_EOL;
    }
  }

  function newsgroup($SERVER, $USER, $PASS, $GROUP=NULL) {
    if ($GROUP == NULL) {
      /* This part will connect to news server and list ALL groups available:       */
      $SERVER = '{'. $SERVER . ':119/nntp}';
      $news = imap_open($SERVER, $USER, $PASS, OP_HALFOPEN);

      echo 'Listing all alt.binaries.teevee.* ['. $SERVER .']'.PHP_EOL;
      $group = imap_getmailboxes($news, $SERVER, "*x264*");

      foreach ($group as $key => $value) {
        echo "($key): ". $value->name .PHP_EOL;
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

//      echo 'Total: '. $total . $PHP_EOL;

//      for ($i = $total-10; $i <= $total; $i++) {
//        $post = imap_header($news, $i);
//        if (!$post->Size) { continue; }
//
//        echo $post->subject .' ('. $post->from[0]->mailbox .'@'. $post->from[0]->host .')' .PHP_EOL;
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
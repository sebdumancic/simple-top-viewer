<!DOCTYPE html>
<html>
 <head>
  <title>PINAC stati</title>
  <meta http-equiv="refresh" content="300" >
  <link href="css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" type="text/css" href="css/style.css" media="screen" />
 </head>

<body>
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
$EXT = 'dat';
$DIR = "./";

$cpu = array();
$mem = array();
$output = array();
$gpu = array();
$index = array();
$name = array();
$utilizationgpu = array();
$utilizationmemory = array();
$memorytotal = array();
$memoryfree = array();
$memoryused = array();

// Open a known directory, and proceed to read its contents
if (is_dir($DIR)) {
    if ($dh = opendir($DIR)) {
        while (($f = readdir($dh)) !== false) {
            $file = $DIR.$f;
            if (is_file($file) && pathinfo($file, PATHINFO_EXTENSION) == $EXT) {
                //print("Loading ".$file."<br />\n");
                include($file);
            }
        }
        closedir($dh);
    }
}

//print_r($output);
//print_r($cpu);

// families of machines
$families = array( 2 => array('himec01', 'himec02'),
                   4 => array('pinac21', 'pinac22', 'pinac23', 'pinac24', 'pinac25', 'pinac26', 'pinac27', 'pinac28', 'pinac29', 'pinac30', 'pinac-a', 'pinac-b', 'pinac-c', 'pinac-d'),
                   3 => array('pinac31', 'pinac32', 'pinac33', 'pinac34', 'pinac35', 'pinac36', 'pinac37', 'pinac38', 'pinac39', 'pinac40'),
                   1 => array('himec03', 'himec04'),
                   5 => array('cudos01')
                 );

$families_notes = array( 0 => 'Various desktop PCs that are (seemingly) not used as such', // machines not in a family
                         2 => '24-thread machines <abbr title="2-socket 12-core 24-thread">[2-12-24]</abbr>, 128 Gb memory',
                         4 => '8-thread machines <abbr title="1-socket 4-core 8-thread">[1-4-8]</abbr>, 16 Gb memory',
                         3 => '4-thread machines <abbr title="1-socket 4-core 4-thread">[1-4-4]</abbr>, 32 Gb memory',
                         1 => '32-thread machines <abbr title="2-socket 16-core 32-thread">[2-16-32]</abbr>, 128 Gb memory',
                         5 => '40-thread machines <abbr title="2-socket 20-core 40-thread">[2-20-40]</abbr>, 128 Gb memory, 4 GPU'
                       );

asort($cpu);
$time_local = time();
$not_responding = array();
$top_users = array();
$all = array_keys($cpu);
$gpu_machines = array_keys($gpu);
//print($gpu_machines)

print('<div class="btn btn-large btn-block disabled" type="button"><b>Basic rules:</b> always leave room for someone to join the party, avoid <a href="http://en.wikipedia.org/wiki/Load_(computing)">high load</a></div>');

print('<div class="left"><h3>Available machines</h3>');
$c = 1;
for ($i = count($families); $i >= 0; $i--) {
  // filter families
  $todo = array();
  if ($i == 0) {
    $todo = $all;
  } else {
    $todo = array_intersect($all, $families[$i]);
    $all = array_diff($all, $todo);
  }

  // filler above families
  printf('<div><h4>%s</h4>', $families_notes[$i]);

  // start table
  print('<table class="table table-bordered table-condensed">');
  print('<tr><th>&nbsp;</th><th>&nbsp;</th><th>%CPU</th><th>%MEM</th><th>Load</th><th colspan="9">Users <i>(bold = cpu-intensive process)</i> </th></tr>');

  // the loop
  foreach($todo as $key) {
    $uss = '';
    foreach ($users[$key] as $u) {
      $uss .= $u.'</td><td>';
      if (array_key_exists($u, $top_users))
        $top_users[$u] += 1;
      else
        $top_users[$u] = 1;
      //$uss .= $u.', ';
    }
    if ($time_local - round(@$time[$key]) > 590) {
      array_push($not_responding, $key);
    } else {
      // property of TR (class="success, error, warning, info")
      $tr_prop = '';

      $myload = $load[$key][0];
      $myusers = count($users[$key]);
      if ($myusers < floatval($myload)*0.75) {
        // too much load, probably swapping, warn
        $tr_prop = ' class="error"';
      }
      if (floatval($cpu[$key]) < 0.1 &&
          floatval($mem[$key]) < 0.1 &&
          floatval($myload) < 0.1)
        $tr_prop = ' class="success"';

      printf('<tr%s><td>%s</td><td><a href="#%s">%s</a></td><td>%s</td><td>%s</td><td><i>%s</i></td><td>%s</td></tr>',
              $tr_prop,
              $c++, $key, $key, $cpu[$key], $mem[$key], $myload, substr($uss, 0, -2));
      //print('<tr><td>'.$key.'</td><td><a href="'.$value.'</td><td>'.$cpu[$value].'</td>
      //      <td>'.$key.'</td><td>'.$mem_keys[$key].'</td><td>'.$mem[$mem_keys[$key]].'</td><tr>');
    }
  }

  print('</table></div>');
}
print('</div>');

if (count($top_users) != 0) {
    arsort($top_users);
    print('<div class="right"><h3>Top users</h3><ol class="unstyled">');
    foreach ($top_users as $u => $c) {
        if ($u != '')
            print('<li><i>'.$u.'</i>: '.$c.' processes</li>');
    }
    print('</ol></div>');
}

//----->GPU
print('<div class="left"><h3>GPU machines</h3>');
$c = 1;
printf('<div><h4>%s</h4>', $families_notes[5]);

// start table
print('<table class="table table-bordered table-condensed">');
print('<tr><th>Machine</th><th>Index</th><th>Name</th><th>%GPU</th><th>%MEM</th><th>Total memory</th><th>Free memory</th><th>Used memory</th></tr>');

foreach($gpu_machines as $key) {
    $gpus = $gpu[$key];
    foreach($gpus as $gpu_key) {
        $ind = $index[$gpu_key];
        $nam = $name[$gpu_key];
        $gpuload = $utilizationgpu[$gpu_key];
        $gpumem = $utilizationmemory[$gpu_key];
        $totmem = $memorytotal[$gpu_key];
        $freemem = $memoryfree[$gpu_key];
        $usedmem = $memoryused[$gpu_key];
        printf('<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',$key,$ind,$nam,$gpuload,$gpumem,$totmem,$freemem,$usedmem);
        }

}

print('</table></div>');
print('</div>');
//<---GPU


// the non-resonding machines, including static ones
//if (true && count($not_responding) > 0) {
if (true) {
    sort($not_responding);
    print('<div class="left"><h3>Unavailable machines</h3><ul>');
    print('<li><i>pinac01-10</i>, reinstalled as desktop machines</li>');
    foreach($not_responding as $key) {
        printf('<li><a href="#%s">%s</a>, no data received since %s</li>',
                $key, $key, date('l jS \of F, G:i:s', round(@$time[$key])));
    }
    print('</ul><p></p></div>');
}

print('<div class="left"><h3>Detailed machine information</h3>');
ksort($output);
foreach ($output as $key => $value) {
    print('<a name="'.$key.'"><h4>'.$key.'</h4></a>');
    print('<table class="table table-bordered table-condensed">');
    print($value);
    print('</table>');
}
print('</div>');


?>

<div class="left">
<b>Source code</b> of this tool available on: <a href="https://github.com/tias/simple-top-viewer">https://github.com/tias/simple-top-viewer</a>
</div>

</body>

</html>

<?php

header("Content-type: text/plain");
header('Content-Disposition: attachment; filename="search_results.tsv"');
header("Pragma: no-cache");
header("Expires: 0");
echo $_POST['results'];

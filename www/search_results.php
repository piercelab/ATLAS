<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="my_css.css" rel="stylesheet" />
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/js/bootstrap.min.js"></script>
    <script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap.min.js"></script>
    <script src="https://cdn.datatables.net/plug-ins/1.12.1/sorting/num-html.js"></script>

    <script type="text/javascript" class="init">
        $(document).ready(function () {
            $('#result_table').DataTable({
                searching: false,
                processin: true,
                "columnDefs": [
                    // fix sorting of Kd column
                    {
                        "type": "num-html",
                        targets: 8
                    }
                ]
            });
        });
    </script>

    <title>ATLAS: Database of TCR-pMHC affinities and structures</title>
</head>

<body>
    <?php require 'ATLAS_functions.php'; ?>
    <nav class="navbar navbar-default">
        <div class="container">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse"
                    data-target=".navbar-collapse">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
            </div>
            <div class="navbar-collapse collapse">
                <ul class="nav navbar-nav">
                    <li class="active"><a href="./">Home</a></li>
                    <li><a href="search.php">Search</a></li>
                    <li><a href="downloads.php">Downloads</a></li>
                    <li><a href="contact.html">Contact</a></li>
                    <li><a href="https://github.com/piercelab/ATLAS">Github</a></li>
                    <li><a href="help.php">Help</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <!-- <div class="container">
            <img class="my_logo" src="atlas_logo_v1.png" />
            <br></br>
        </div> -->
    <div class="container">
        <div class="page-header">
            <h3>Search Results</h3>
        </div>
    </div>

    <?php
        $link = database_connect();
        // Check if input is valid
    if (is_numeric($_POST['dG']) && ! is_numeric($_POST['peptide'])) {
        if (isset($_POST['SEL'])) {
            // Set query columns
            $query_columns = array("TCRname", "TCR_mut", "TCR_mut_chain", "MHCname", "MHC_mut", "MHC_mut_chain", "PEPseq", "PEP_mut", "Kd_microM", "DeltaG_kcal_per_mol", "true_PDB", "template_PDB", "PMID");
            $query_columns_str = join(', ', $query_columns);
            $col_count = 13;

            // Get all parameters from search form
            $search_params = array();

            if (isset($_POST['TCR'])) {
                if ($_POST['TCR'] != 'all') {
                    $search_params[] = "(TCRname = '" . $_POST['TCR'] . "')";
                }
            }
            if (isset($_POST['TRAV'])) {
                if ($_POST['TRAV'] != 'all') {
                    $query = "SELECT TCRname FROM TCRs WHERE TRAV='" . $_POST['TRAV'] . "';";
                    $TRAV_result = $link->query($query) or die($link->lastErrorMsg());
                    $i = 0;
                    while ($row = $TRAV_result->fetchArray()) {
                        $TRAVtcrs[$i] = $row['TCRname'];
                        $i++;
                    }
                    $or_string = '';
                    for ($i = 0; $i < count($TRAVtcrs); $i++) {
                        $or_string .= "TCRname = '" . $TRAVtcrs[$i] . "'";
                        if ($i < count($TRAVtcrs) - 1) {
                            $or_string .= " OR ";
                        }
                    }
                    $search_params[] = "(" . $or_string . ")";
                }
            }
            if (isset($_POST['TRBV'])) {
                if ($_POST['TRBV'] != 'all') {
                    $query = "SELECT TCRname FROM TCRs WHERE TRBV='" . $_POST['TRBV'] . "';";
                    $TRBV_result = $link->query($query) or die($link->lastErrorMsg());
                    $i = 0;
                    while ($row = $TRBV_result->fetchArray()) {
                        $TRBVtcrs[$i] = $row['TCRname'];
                        $i++;
                    }
                    $or_string = '';
                    for ($i = 0; $i < count($TRBVtcrs); $i++) {
                        $or_string .= "TCRname = '" . $TRBVtcrs[$i] . "'";
                        if ($i < count($TRBVtcrs) - 1) {
                            $or_string .= " OR ";
                        }
                    }
                    $search_params[] = "(" . $or_string . ")";
                }
            }

            if (isset($_POST['MHCclass'])) {
                if ($_POST['MHCclass'] != 'all') {
                    $query = "SELECT MHCname FROM MHCs WHERE class='" . $_POST['MHCclass'] . "';";
                    $MHCclass_result = $link->query($query) or die($link->lastErrorMsg());
                    $i = 0;
                    while ($row = $MHCclass_result->fetchArray()) {
                        $class_mhcs[$i] = $row['MHCname'];
                        $i++;
                    }
                    $or_string = '';
                    for ($i = 0; $i < count($class_mhcs); $i++) {
                        $or_string .= "(MHCname LIKE '%" . $class_mhcs[$i] .
                                "%' OR MHCname_PDB LIKE '%" . $class_mhcs[$i] . "%')";
                        if ($i < count($class_mhcs) - 1) {
                            $or_string .= " OR ";
                        }
                    }
                    $search_params[] = "(" . $or_string . ")";
                }
            }

            if (isset($_POST['MHCname'])) {
                if ($_POST['MHCname'] != 'all') {
                    $search_params[] = "(MHCname LIKE '%" . $_POST['MHCname'] .
                        "%' OR MHCname_PDB LIKE '%" . $_POST['MHCname'] . "%')";
                }
            }

            if (isset($_POST['dG'])) {
                if ($_POST['dG'] != '0.00') {
                    $search_params[] = "(DeltaG_kcal_per_mol < '" . $_POST['dG'] . "')";
                }
            }
            if (isset($_POST['peptide'])) {
                if ($_POST['peptide'] != 'all') {
                    $search_params[] = "(PEPseq LIKE '%" . $_POST['peptide'] . "%')";
                }
            }

            if (isset($_POST['pdbid'])) {
                if ($_POST['pdbid'] != 'all') {
                    $search_params[] = "(true_PDB LIKE '%" . $_POST['pdbid'] .
                        "%' OR template_PDB LIKE '%" . $_POST['pdbid'] . "%')";
                }
            }


            $where_query = join(' AND ', $search_params);
            if (empty($search_params)) {
                $query = "SELECT {$query_columns_str} FROM Mutants;";
            } else {
                $query = "SELECT {$query_columns_str} FROM Mutants WHERE {$where_query};";
            }
            $result = $link->query($query) or die($link->lastErrorMsg());
        } ?>
    <div class="container">
        <table id="result_table" class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>TCR name</th>
                    <th>TCR mutation</th>
                    <th>TCR mutation chain</th>
                    <th>MHC allele</th>
                    <th>MHC mutation</th>
                    <th>MHC mutation chain</th>
                    <th>Peptide</th>
                    <th>Peptide mutation</th>
                    <th><span style="white-space: nowrap;">K<sub>D</sub> (&#956M)</span></th>
                    <th><span style="white-space: nowrap;">&#916G (kcal mol<sup>-1</sup>)</span></th>
                    <th>PDB</th>
                    <th>Template PDB</th>
                    <th>PMID</th>
                </tr>
                <?php
                    // Write results to string for download
                    $download_results = join("\t", $query_columns) . "\n"; ?>
            </thead>
            <tbody>
                <?php
                while ($row = $result->fetchArray()) {
                    echo "<tr>";
                    for ($i = 0; $i < $col_count; $i++) {
                        echo "<td>";
                        if ($row[$query_columns[$i]] == "PMID") {
                            $download_results .= $row[$query_columns[$i]];
                        } else {
                            $download_results .= $row[$query_columns[$i]] . "\t";
                        }
                        if ($query_columns[$i] == "MHCname") {
                            ?>
                <span style="white-space: nowrap;">
                    <?php
                            $MHCname_arr = explode("|", $row[$query_columns[$i]]);
                            foreach ($MHCname_arr as $allele) {
                                echo $allele; ?> <br></br> <?php
                            } ?>
                </span>
                <?php
                        } elseif ($query_columns[$i] == "PMID") {
                            echo '<a href="http://www.ncbi.nlm.nih.gov/pubmed/' . $row[$query_columns[$i]] . '">' . $row[$query_columns[$i]] . '</a>';
                        } elseif ($query_columns[$i] == "true_PDB") {
                            echo '<a href="3D_viewer.php?pdb=' . $row[$query_columns[$i]] . '">' . $row[$query_columns[$i]] . '</a>';
                        } elseif ($query_columns[$i] == "template_PDB") {
                            if ($row['MHC_mut_chain'] == "") {
                                $tpdb_mhc_chain = "nan";
                            } else {
                                $tpdb_mhc_chain = preg_replace('/\s+/', '', $row['MHC_mut_chain']);
                                $tpdb_mhc_chain = preg_replace('/\|/', '.', $tpdb_mhc_chain);
                            }
                            if ($row['TCR_mut_chain'] == "") {
                                $tpdb_tcr_chain = "nan";
                            } else {
                                $tpdb_tcr_chain = preg_replace('/\s+/', '', $row['TCR_mut_chain']);
                                $tpdb_tcr_chain = preg_replace('/\|/', '.', $tpdb_tcr_chain);
                            }
                            $tpdb_pdb = preg_replace('/\s+/', '', $row[$query_columns[$i]]);
                            $tpdb_mhc = preg_replace('/\s+/', '', $row['MHC_mut']);
                            $tpdb_mhc = preg_replace('/\|/', '.', $tpdb_mhc);
                            $tpdb_tcr = preg_replace('/\s+/', '', $row['TCR_mut']);
                            $tpdb_tcr = preg_replace('/\|/', '.', $tpdb_tcr);
                            $tpdb_pep = preg_replace('/\s+/', '', $row['PEP_mut']);
                            $tpdb_pep = preg_replace('/\|/', '.', $tpdb_pep);

                            echo '<a href="3D_viewer_designed.php?pdb=' . $tpdb_pdb . '&mhc_mut=' . $tpdb_mhc
                            . '&mhc_chain=' . $tpdb_mhc_chain . '&tcr_mut=' . $tpdb_tcr . '&tcr_chain=' . $tpdb_tcr_chain
                            . '&pep_mut=' . $tpdb_pep . '">' . $tpdb_pdb . '</a>';
                        } else {
                            echo $row[$query_columns[$i]];
                        }
                        echo "</td>";
                    }
                    $download_results .= "\n";
                    echo "</tr>";
                } ?>
            </tbody>
        </table>
    </div>
    <div class="container">
        <?php
            echo '
                <form action="tables/results_table.php" method="POST">
                <input type="hidden" name="results" value="' . $download_results . '"> 
                <button type="submit" class="btn btn-primary">
                    <span class="glyphicon glyphicon-download"></span> Download Results
                </button>
                </form>'; ?>
    </div>
    <br><br>
    <?php
    } else {
        ?>
    <div class="well">
        <p> Invalid search selection </p>
    </div>
    <?php
    }
    ?>
    <div class="container">
        <hr>
        <footer>
            <div class="row">
                <div class="col-sm-4" align="center">
                    <img src="logos/umasslogoformal.gif" width='180' />
                </div>
                <div class="col-sm-4" align="center">
                    <img src="logos/1_university_mark.jpg" width='225' />
                </div>
                <div class="col-sm-4" align="center">
                    <img src="logos/IBBR-Logo_Long.png" width='250' />
                </div>
            </div>
            <br></br>
        </footer>
    </div>
</body>

</html>
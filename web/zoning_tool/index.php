<?php
	session_start();
?>
<html>

<head>

	<title>Upload Student Addresses</title>

	<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>



	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">



	<!-- Optional theme -->

	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css" integrity="sha384-rHyoN1iRsVXV4nD0JutlnGaslCJuC7uwjduW9SVrLvRYooPp2bWYgmgJQIXwl/Sp" crossorigin="anonymous">

	<link rel="stylesheet" type="text/css" href="style.css">

	<script src="https://use.fontawesome.com/722d4e7356.js"></script>

</head>

<body>



	<nav class="navbar navbar-default navbar-fixed-top">

		<div class="container-fluid">

			<div class="navbar-header">

				<button type="button" class="collapsed navbar-toggle" data-toggle="collapse" data-target="#bs-example-navbar-collapse-7" aria-expanded="false"> <span class="sr-only">Toggle navigation</span>

					<span class="icon-bar"></span> <span class="icon-bar"></span> <span class="icon-bar"></span>

				</button>

				<img src="http://wbrc.images.worldnow.com/images/8398202_G.jpg" height="45px" class="topimg">

			</div>



			<div class="collapse navbar-collapse" id="bs-example-navbar-collapse-7">

				<ul class="nav navbar-nav">

					<li class="active"><a href="#">Schools</a></li>

				</ul>

			</div>

		</div>

	</nav>



	<div class="container-fluid">

		<div id="main_container">

			<div id="info_paragraph">

				<h3>Tuscaloosa City Schools Zoning Tool</h3>

				<p>This tool is used to find zoning information for students within the Tuscaloosa City School System.</br>

				Please use the <a href="tusc.xls">template</a> to format your spreadsheet before proceeding.</p>

			</div>

			<div id="top_row">

				<div class="col-md-3">

					<form action="" method="post" enctype="multipart/form-data">

						<h4>Select address file:</h4>

						<input type="file" name="excel"><p></p>



						<label class="radio-inline">

						  <input type="radio" name="syear" value="current">Current Year

						</label>

						<label class="radio-inline">

						  <input type="radio" name="syear" value="next">Next Year

						</label>

						<label class="radio-inline">

						  <input type="radio" name="syear" value="both">Both

						</label>

						<p></p>

						<button type="submit" name="submit" class="btn btn-success">Upload File</button>

					</form>

				</div>

				<div class="col-md-6"></div>

				<div class="col-md-3" id="newlist">

				</div>

			</div>

		</div>

	</div>



	<div id="progress"></div>



	<?php

	ini_set('memory_limit', '128M');

	if ($_POST)

	{



		$table = '<div class="table-responsive">

			<table class="table table-striped table-bordered">

			<th>#</th>

			<th>Number</th>

			<th>Last Name</th>

			<th>First Name</th>

			<th>Race</th>

			<th>Grade</th>

			<th>School</th>

			<th>Address</th>

			<th>ZIP Code</th>
			';

			if($_POST['syear'] == '')

			{

				$table .= '

				<th>Elementary</th>
				<th>Elementary Choice</th>
				<th>Middle</th>
				<th>Middle Choice</th>
				<th>High</th>
				<th>High Choice</th>';

			} else if ($_POST['syear'] == 'current') {

				$table .= '<th>Current Year</th>';
				$table .= '<th>Current Choice</th>';

			} else if ($_POST['syear'] == 'next') {

				$table .= '<th>Next Year</th>';
				$table .= '<th>Next Choice</th>';

			} else if ($_POST['syear'] == 'both') {

				$table .= '<th>Current</th>';
				$table .= '<th>Current Choice</th>';

				$table .= '<th>Next Year</th>';
				$table .= '<th>Next Choice</th>';

			}

		$table .='</div>';

		//$homepage = file_get_contents($_FILES['excel']['tmp_name']);
		//echo $homepage;die;



		include('excel_reader2.php');

		$data = new Spreadsheet_Excel_Reader();



		$data->read($_FILES['excel']['tmp_name']);

		//$excelData = json_decode(json_encode($data), true);

		$excelData = get_object_vars($data);



		//pr($excelData['sheets'][0]['cells']);



		// create table fields name

		$fieldCount = count($excelData['sheets'][0]['cells']);

		$allFields = array();

		$allFields = $excelData['sheets'][0]['cells'][1];



		$updatedData = [];



		$num = 1;

		//$fieldCount = 10;

		for($i = 2; $i <= $fieldCount; $i++) {

			$Perc = round(($i / $fieldCount) * 100);

			// progress bar

			echo '<script language="javascript">

			document.getElementById("progress").innerHTML="<div class=\"progress\"><div class=\"progress-bar progress-bar-primary\" role=\"progressbar\" aria-valuenow=\"'.$Perc.'\" aria-valuemin=\"0\" aria-valuemax=\"100\" style=\"width:'.$Perc.'%\">'.$Perc.'% Completed</div></div>";

				</script>';



			// download button

			if ($i == $fieldCount)

			{

				echo '<script language="javascript">

			document.getElementById("newlist").innerHTML="<a href=\"download.php\" class=\"btn btn-success download\"><i class=\"fa fa-download\" aria-hidden=\"true\"></i> Download the New List</a>";

				</script>';

			}

			$updatedData[$i][12] = '';
			$updatedData[$i][13] = '';
			$updatedData[$i][14] = '';
			$updatedData[$i][15] = '';
			$updatedData[$i][16] = '';
			$updatedData[$i][17] = '';

			foreach ($excelData['sheets'][0]['cells'][$i] as $key => $val) {

				// assign keys

				if(in_array($i, [12,13,14,15,16,17]))

				{

					unset($excelData['sheets'][0]['cells'][$i]);

				}

				$updatedData[$i][$key] = $val;

				// if there is an address

				if ($key == '7' && $val != '')

				{
					$address = str_ireplace('Tuscaloosa, AL', '', $val);

					$address = str_ireplace('Northport, AL', '', $address);

					$address = str_ireplace('Cottondale, AL', '', $address);

					$address_basic = trim($address);

					$abbrevs = [

						'/ GDNS\b/i' => ' Gardens',

						'/ HTS\b/i' => ' heights',

						'/ SQ\b/i' => ' square',

						'/ VLG\b/i' => ' village',

						'/ GRV\b/i' => ' grove',

						'/ RDG\b/i' => ' ridge',

						'/ HWY\b/i' => ' hw',

						'/ HLS\b/i' => ' hills',

						'/ TER\b/i' => ' terrace'

					];

					foreach($abbrevs as $k => $v)
					{
						$address_basic = preg_replace($k, $v, $address_basic);
					}

					$address_basic = strtoupper($address_basic);

					$pattern = '/ apt [a-z0-9 ]+/i';

					$pattern2 = '/ lot [a-z0-9 ]+/i';

					$address_basic = preg_replace($pattern, '', $address_basic);

					$address_basic = preg_replace($pattern2, '', $address_basic);

					$address_basic = str_replace(' ', '+', $address_basic);

					$address_basic = preg_replace('/\s+/', ' ', $address_basic);

					$address_basic = preg_replace( '/^(\d+)[a-zA-Z]/', '$1', $address_basic );

					$fake_address = $address_basic.' | '.$zip_code;

				}

				if ($key == '11' && $val != '')

				{
					$zip_code = $val;

					$fake_address = $address_basic.' | '.$zip_code;

				}

			}

			if ($address_basic)

			{
				$zip_code = $excelData['sheets'][0]['cells'][$i][11];
				// get json data
				$url = "https://arcgis.tuscaloosa-al.gov/arcgis/rest/services/Spillman/Addresses_by_Status_Published/MapServer/3/query";
				$url .= "?where=address_basic+LIKE+'".$address_basic.urlencode('%'). "'&zip_code='".$zip_code."'&outFields=address_basic&f=json";

				$content = file_get_contents($url);
				$json = json_decode($content, true);

				if( count( $json['features'] ) == 0 ){

					$address_basic = preg_replace('/\+\w+$/', '', $address_basic );
					$url = "https://arcgis.tuscaloosa-al.gov/arcgis/rest/services/Spillman/Addresses_by_Status_Published/MapServer/3/query";
					$url .= "?where=address_basic+LIKE+'".$address_basic.urlencode('%'). "'&zip_code='".$zip_code."'&outFields=address_basic&f=json";

			 	   	$content = file_get_contents($url);
			 	   	$json2 = json_decode($content, true);

				 	if( count( $json2['features'] ) == 1 ){
				 		$json = $json2;
				 	} else if( count( $json2['features'] ) > 1 ){
				 		$updatedData[$i][12] = 'MULTIPLE MATCHES';
				 		$updatedData[$i][14] = 'MULTIPLE MATCHES';
				 		$updatedData[$i][16] = 'MULTIPLE MATCHES';
				 	}
				}

				if( isset( $json['features'][0]['geometry'] ) ){

					$url = "https://arcgis.tuscaloosa-al.gov/arcgis/rest/services/CitySchools/"
			            ."City_Schools_Districts_1819/MapServer/0/query?"
			            ."&geometryType=esriGeometryPoint"
			            ."&spatialRel=esriSpatialRelIntersects"
			            ."&outFields=elem%2Cmiddle%2Chigh%2CES_choice%2CMS_choice%2CHS_choice"
			            ."&returnGeometry=true"
			            ."&returnTrueCurves=false"
			            ."&returnIdsOnly=false"
			            ."&returnCountOnly=false"
			            ."&returnZ=false"
			            ."&returnM=false"
			            ."&returnDistinctValues=false"
			            ."&returnExtentsOnly=false"
			            ."&returnGeometry=false"
			            ."&f=json"
			            ."&geometry="
						. urlencode( $json['features'][0]['geometry']['x'] .",". $json['features'][0]['geometry']['y'] );
					$content = file_get_contents($url);
					$json = json_decode($content, true);

					$updatedData[$i][12] = ( $json['features'][0]['attributes']['elem'] )
						? ucwords( strtolower($json['features'][0]['attributes']['elem']) )
						: 'N/A';
					$updatedData[$i][13] = ( $json['features'][0]['attributes']['ES_choice'] )
						? ucwords( strtolower($json['features'][0]['attributes']['ES_choice']) )
						: '';


					$updatedData[$i][14] = ($json['features'][0]['attributes']['middle'])
						? ucwords(strtolower($json['features'][0]['attributes']['middle']))
						: 'N/A';
					$updatedData[$i][15] = ( $json['features'][0]['attributes']['MS_choice'] )
						? ucwords( strtolower($json['features'][0]['attributes']['MS_choice']) )
						: '';

					$updatedData[$i][16] = ($json['features'][0]['attributes']['high'])
						? ucwords(strtolower($json['features'][0]['attributes']['high']))
						: 'N/A';
					$updatedData[$i][17] = ( $json['features'][0]['attributes']['HS_choice'] )
						? ucwords( strtolower($json['features'][0]['attributes']['HS_choice']) )
						: '';
				}
			}

			$elem = [1, 2, 3, 4, 5];

			$mid = [6,7,8];

			$high = [9,10,11,12];

			$currentGrade = ltrim($updatedData[$i][5], '0');

			$nextGrade = $currentGrade + 1;



			if($_POST['syear'] == 'current')

			{

				$yearWanted = '';

			}


			$currentSchool = '';
			$currentChoice = '';
			if (in_array($currentGrade, $elem))

			{
				$currentSchool = $updatedData[$i][12];
				$currentChoice = $updatedData[$i][13];

			} else if (in_array($currentGrade, $mid)) {

				$currentSchool = $updatedData[$i][14];
				$currentChoice = $updatedData[$i][15];

			} else if (in_array($currentGrade, $high)) {

				$currentSchool = $updatedData[$i][16];
				$currentChoice = $updatedData[$i][17];

			}


			$nextSchool = '';
			$nextChoice = '';
			if (in_array($nextGrade, $elem))

			{
				$nextSchool = $updatedData[$i][12];
				$nextChoice = $updatedData[$i][13];

			} else if (in_array($nextGrade, $mid)) {

				$nextSchool = $updatedData[$i][14];
				$nextChoice = $updatedData[$i][15];

			} else if (in_array($nextGrade, $high)) {

				$nextSchool = $updatedData[$i][16];
				$nextChoice = $updatedData[$i][17];

			}



			$address_basic = '';

			$table .= '<tr>

			<td>'.$num.'</td>

			<td>'.$updatedData[$i][1].'</td>

			<td>'.$updatedData[$i][2].'</td>

			<td>'.$updatedData[$i][3].'</td>

			<td>'.$updatedData[$i][4].'</td>

			<td>'.$updatedData[$i][5].'</td>

			<td>'.$updatedData[$i][6].'</td>

			<td>'.$updatedData[$i][7].'</td>

			<td>'.$updatedData[$i][11].'</td>';

			if($_POST['syear'] == '')

			{

				$table .= '

				<td>'.$updatedData[$i][12].'</td>
				<td>'.$updatedData[$i][13].'</td>
				<td>'.$updatedData[$i][14].'</td>
				<td>'.$updatedData[$i][15].'</td>
				<td>'.$updatedData[$i][16].'</td>
				<td>'.$updatedData[$i][17].'</td>';

			} else if ($_POST['syear'] == 'current') {

				$table .= '<td>'.$currentSchool.'</td>';
				$table .= '<td>'.$currentChoice.'</td>';

			} else if ($_POST['syear'] == 'next') {

				$table .= '<td>'.$nextSchool.'</td>';
				$table .= '<td>'.$nextChoice.'</td>';

			} else if ($_POST['syear'] == 'both') {

				$table .= '<td>'.$currentSchool.'</td>';
				$table .= '<td>'.$currentChoice.'</td>';

				$table .= '<td>'.$nextSchool.'</td>';
				$table .= '<td>'.$nextChoice.'</td>';

			}



			$table .='<tr>';



			$num++;
		}

		$table .= '</table>';

		$_SESSION["table"] = $table;

		echo $table;

	}

	?>



	<?php

	function pr($var = array())

	{

		echo '<pre>';

		print_r($var);

		echo '</pre>';

	}

	?>
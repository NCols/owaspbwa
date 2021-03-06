<?php

//INCLUDES
include_once('header.php');
include_once('config.php');
include_once('gtdfuncs.php');

//CONNECT TO DATABASE
$connection = mysql_connect($host, $user, $pass) or die ("unable to connect");
mysql_select_db($db) or die ("unable to select database!");


//GET URL VARIABLES
$type=$_GET["type"]{0};
$pType=$_GET["pType"]{0};
if ($pType!="s") $pType="p";
if ($_GET['contextId']>0) $contextId=(int) $_GET['contextId'];
else $contextId=(int) $_POST['contextId'];
if ($_GET['categoryId']>0) $categoryId=(int) $_GET['categoryId'];
else $categoryId=(int) $_POST['categoryId'];
if ($_GET['timeId']>0) $timeId=(int) $_GET['timeId'];
else $timeId=(int) $_POST['timeId'];


if ($ptype=='s') $ptypequery='y';
else $ptypequery='n';

//Set page titles
if ($type=="a") {
	$typename="Actions";
	$typequery="a";
	}
elseif ($type=="n") {
	$typename="Next Actions";
	$display="nextonly";
	$typequery="a";
	}
elseif ($type=="r") {
	$typename="References";
	$typequery="r";
	}
elseif ($type=="w") {
	$typename="Waiting On";
	$typequery="w";
	}
else {
	$typename="Items";
	$typequery="a";
	}
	
//SQL CODE
//select all contexts for dropdown list
$query = "SELECT contextId, name, description  FROM context ORDER BY name ASC";
$result = mysql_query($query) or die("Error in query");
$cshtml="";
while($row = mysql_fetch_assoc($result)) {
	$cshtml .= '	<option value="'.$row['contextId'].'" title="'.htmlspecialchars(stripslashes($row['description'])).'"';
	if($row['contextId']==$contextId) $cshtml .= ' SELECTED';
	$cshtml .= '>'.stripslashes($row['name'])."</option>\n";
}
mysql_free_result($result);

//select time contexts for dropdown list
$query = "SELECT timeframeId, timeframe, description FROM timeitems";
$timeframeResults = mysql_query($query) or die ("Error in query");
$thtml="";
while($row = mysql_fetch_assoc($timeframeResults)) {
    $thtml .= '	<option value="'.$row['timeframeId'].'" title="'.htmlspecialchars(stripslashes($row['timeframeId'])).'"';
    if($row['timeframeId']==$timeId) $thtml .=' SELECTED';
    $thtml .= '>'.stripslashes($row['timeframe'])."</option>\n";
}


//select all nextactions for test
$query = "SELECT projectId, nextaction FROM nextactions";
$result = mysql_query($query) or die ("Error in query");
$nextactions = array();
while ($nextactiontest = mysql_fetch_assoc($result)) {
	//populates $nextactions with itemIds using projectId as key
	$nextactions[$nextactiontest['projectId']] = $nextactiontest['nextaction'];
}

//select all categories for dropdown list
$query = "SELECT categories.categoryId, categories.category, categories.description from categories ORDER BY categories.category ASC";
$result = mysql_query($query) or die("Error in query");
$cashtml="";
while($row = mysql_fetch_assoc($result)) {
	$cashtml .= '	<option value="'.$row['categoryId'].'" title="'.htmlspecialchars(stripslashes($row['description'])).'"';
	if($row['categoryId']==$categoryId) $cashtml .= ' SELECTED';
	$cashtml .= '>'.stripslashes($row['category'])."</option>\n";
}
mysql_free_result($result);


//Select items
$catquery = "";
$contextquery = "";
$timequery ="";
if ($contextId != NULL) $contextquery = "AND itemattributes.contextId = '$contextId'";
if ($categoryId != NULL) $catquery = " AND projectattributes.categoryId = '$categoryId'";
if ($timeId !=NULL) $timequery = "AND itemattributes.timeframeId ='$timeId'";

$query = "SELECT itemattributes.projectId, projects.name AS pname, items.title, items.description, itemstatus.dateCreated, 
	context.contextId, context.name AS cname, items.itemId, itemstatus.dateCompleted, itemattributes.deadline, 
	itemattributes.repeat, itemattributes.suppress, itemattributes.suppressUntil 
	FROM items, itemattributes, itemstatus, projects, projectattributes, projectstatus, context 
	WHERE itemstatus.itemId = items.itemId AND itemattributes.itemId = items.itemId 
	AND itemattributes.contextId = context.contextId AND itemattributes.projectId = projects.projectId 
	AND projectattributes.projectId=itemattributes.projectId AND projectstatus.projectId = itemattributes.projectId 
	AND itemattributes.type = '$typequery' " .$catquery.$contextquery.$timequery. " AND projectattributes.isSomeday='$ptypequery'
	AND (itemstatus.dateCompleted IS NULL OR itemstatus.dateCompleted = '0000-00-00')
	AND (projectstatus.dateCompleted IS NULL OR projectstatus.dateCompleted = '0000-00-00') 
	AND ((CURDATE() >= DATE_ADD(itemattributes.deadline, INTERVAL -(itemattributes.suppressUntil) DAY))
		OR itemattributes.suppress='n'
		OR ((CURDATE() >= DATE_ADD(projectattributes.deadline, INTERVAL -(projectattributes.suppressUntil) DAY))))
	ORDER BY projects.name, itemattributes.deadline, items.title";


$result = mysql_query($query) or die ("Error in query");

//PAGE DISPLAY CODE
	echo '<h2><a href="item.php?type='.$type.'" title="Add new '.str_replace("s","",$typename).'">'.$typename."</a></h2>\n";
	echo '<form action="listItems.php?type='.$type.'" method="post">'."\n";
	echo "<p>Category:&nbsp;\n";
	echo '<select name="categoryId" title="Filter items by project category">'."\n";
	echo '	<option value="">All</option>'."\n";
	echo $cashtml;
	echo "</select>\n";
	echo "&nbsp;&nbsp;&nbsp;\nContext:&nbsp;\n";
	echo '<select name="contextId" title="Filter items by context">'."\n";
	echo '	<option value="">All</option>'."\n";
	echo $cshtml;
	echo "</select>\n";
	echo "&nbsp;&nbsp;&nbsp;\nTime:&nbsp;\n";
	echo '<select name="timeId" title="Filter items by time context">'."\n";
	echo '	<option value="">All</option>'."\n";
	echo $thtml;
	echo "</select>\n";
	echo '<input type="submit" class="button" value="Filter" name="submit" title="Filter '.$typename.' by category and/or contexts">'."\n";
	echo "</p>\n";
	echo "</form>\n\n";

	if (mysql_num_rows($result) > 0) {
		$tablehtml="";		
		while($row = mysql_fetch_assoc($result)){

			$showme="y";
			//filter out all but nextactions if $display=nextonly
			if (($display=='nextonly')  && !($key=array_search($row['itemId'],$nextactions))) $showme="n";

			if($showme=="y") {
			
				$tablehtml .= "	<tr>\n";
				$tablehtml .= '		<td><a href = "projectReport.php?projectId='.$row['projectId'].'"title="Go to '.htmlspecialchars(stripslashes($row['pname'])).' project report">'.stripslashes($row['pname'])."</a></td>\n";

				//if nextaction, add icon in front of action (* for now)
				if ($key = array_search($row['itemId'],$nextactions)) $tablehtml .= '		<td><a href = "item.php?itemId='.$row['itemId'].'" title="Edit '.htmlspecialchars(stripslashes($row['title'])).'">*&nbsp;'.stripslashes($row['title'])."</td>\n";
				else $tablehtml .= '		<td><a href = "item.php?itemId='.$row['itemId'].'" title="Edit '.htmlspecialchars(stripslashes($row['title'])).'">'.stripslashes($row['title']).'</td>';
				$tablehtml .= '		<td>'.nl2br(stripslashes($row['description']))."</td>\n";
				$tablehtml .= '		<td><a href = "editContext.php?contextId='.$row['contextId'].'" title="Go to '.htmlspecialchars(stripslashes($row['cname'])).' context report">'.stripslashes($row['cname'])."</td>\n";
				$tablehtml .= "		<td>";

				if(($row['deadline']) == "0000-00-00" || $row['deadline'] ==NULL) $tablehtml .= "&nbsp;";
				elseif(($row['deadline']) < date("Y-m-d")) $tablehtml .= '<font color="red"><strong title="Item overdue">'.date("D M j, Y",strtotime($row['deadline'])).'</strong></font>';  //highlight overdue actions
				elseif(($row['deadline']) == date("Y-m-d")) $tablehtml .= '<font color="green"><strong title="Item due today">'.date("D M j, Y",strtotime($row['deadline'])).'</strong></font>'; //highlight actions due today
				else $tablehtml .= date("D M j, Y",strtotime($row['deadline']));
				
				$tablehtml .= "</td>\n";
				if ($row['repeat']=="0") $tablehtml .= "		<td>--</td>\n";
				else $tablehtml .= "		<td>".$row['repeat']."</td>\n";
	            $tablehtml .= '		<td align="center"><input type="checkbox" align="center" title="Complete '.htmlspecialchars(stripslashes($row['title'])).'" name="completedNas[]" value="';
                $tablehtml .= $row['itemId'];
                $tablehtml .= '" /></td>'."\n";
				$tablehtml .= "	</tr>\n";
			}
		}

		if ($tablehtml!="") {
			echo '<form action="processItemUpdate.php" method="post">'."\n";
			echo "<table class='datatable'>\n";
			echo "	<thead>\n";
			echo "		<td>Project</td>\n";
			echo "		<td>".$typename."</td>\n";
			echo "		<td>Description</td>\n";
			echo "		<td>Context</td>\n";
			echo "		<td>Deadline</td>\n";
			echo "		<td>Repeat</td>\n";
			echo "		<td>Completed</td>\n";
			echo "	</thead>\n";
			echo $tablehtml;
			echo "</table>\n";
			echo '<input type="hidden" name="type" value="'.$type.'" />'."\n";
			echo '<input type="hidden" name="contextId" value="'.$contextId.'" />'."\n";
			echo '<input type="hidden" name="timeId" value="'.$timeId.'" />'."\n";
			echo '<input type="hidden" name="referrer" value="i" />'."\n";
			echo '<input type="submit" class="button" value="Complete '.$typename.'" name="submit">'."\n";
			echo "</form>\n";
		}else{ 
			$message="Nothing was found.";
			nothingFound($message);
		}
	}else{
		$message="You have no actions remaining.";
		$prompt="Would you like to create a new action?";
		$yeslink="item.php?type=a";
		nothingFound($message,$prompt,$yeslink);
	}


	mysql_free_result($result);
	mysql_close($connection);
	include_once('footer.php');
?>

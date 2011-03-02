<?php
/**
 * Zend Model Mapper
 * Copyright 2011 Matt McCormick
 * http://mattmccormick.ca
 * matt@mattmccormick.ca
 *
 * You may use this code freely in your own projects.  If it helped save you time
 * or frustration, a donation would be much appreciated.  Donations can be made
 * through my website or via Paypal to my email address.
 *
 * The PEAR package Image_GraphViz is required for this script to work.
 *
 * This script currently assumes a setup.php file under application/configs/ which sets up
 * Zend Framework and bootstraps the application.  Modify to setup your Zend environment.
 *
 * The diagrams will be output in PDF folder under application/diagrams/ZendModelMapper/YYYY-MM-DD.pdf
 */

require_once 'Image/GraphViz.php';


// Replace/Alter these lines with any code needed to setup application //
include realpath(dirname(__FILE__)) . '/../configs/setup.php';
// End Replace //


$directory = new RecursiveDirectoryIterator(APPLICATION_PATH);
$iterator = new RecursiveIteratorIterator($directory);
$regex = new RegexIterator($iterator, '/^.+models.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

$graph = new Image_GraphViz(true, array(
	'rankdir' => 'LR'
));

$models = array();

foreach ($regex as $name => $arr) {
    require_once $name;
    $file = new Zend_Reflection_File($name);
    $classes = $file->getClasses();

    foreach ($classes as $class) {
    	if ($class->isSubclassOf('Zend_Db_Table_Abstract')) {
    		$model = $class->newInstance();
    		$models[$class->getName()] = $model->info();
    	}
    }
}

$nodes = array();	// key: table_name value: array of columns
$edges = array();

foreach ($models as $model) {
	$name = $model[Zend_Db_Table_Abstract::NAME];

	if (!isset($nodes[$name])) {
		$nodes[$name] = array();	// display all tables even isolated ones
	}

	$ref = $model[Zend_Db_Table_Abstract::REFERENCE_MAP];

	foreach ($ref as $ref_name => $map) {

		$class = $map[Zend_Db_Table_Abstract::REF_TABLE_CLASS];

		$table = new $class;
		$table_name = $table->info(Zend_Db_Table_Abstract::NAME);

		if ($table_name === $name) {
			continue;	// cannot handle self-referential references
		}

		$ref_column = getArray($map[Zend_Db_Table_Abstract::REF_COLUMNS]);
		$column = getArray($map[Zend_Db_Table_Abstract::COLUMNS]);

		for ($i = 0; $i < count($ref_column); $i++) {
			addToArray($nodes, $name, $column[$i]);
			addToArray($nodes, $table_name, $ref_column[$i]);

			$edges[] = array(
				'edge' => array($name => $table_name),
				'port' => array(
					$name => $column[$i],
					$table_name => $ref_column[$i]
				)
			);
		}
	}
}

foreach ($nodes as $name => $cols) {

	$label = "<name> {$name}";

	foreach ($cols as $col) {
		$label .= " | <{$col}> {$col}";
	}

	$graph->addNode($name, array(
		'shape' => 'record',
		'label' => $label
	));
}

foreach ($edges as $edge) {
	$graph->addEdge($edge['edge'], array(), $edge['port']);
}

$dot = $graph->saveParsedGraph();

$output_folder = APPLICATION_PATH . '/diagrams/ZendModelMapper/';
if (!file_exists($output_folder)) {
	mkdir($output_folder, 0777, true);
	chmod($output_folder, 0777);
}

$result = $graph->renderDotFile($dot, $output_folder . date('Y-m-d') . '.pdf', 'pdf');

if ($result) {
	echo "ZendModelMapper file saved to {$output_folder}\n";
} else {
	echo "There was an error saving the file.\n";
}


//////////// FUNCTIONS /////////////////
function getArray($val) {
	if (!is_array($val)) {
		$val = array($val);
	}

	return $val;
}

function addToArray(array &$nodes, $key, $value) {
	if (!isset($nodes[$key])) {
		$nodes[$key] = array($value);
	} else if (!in_array($value, $nodes[$key])) {
		$nodes[$key][] = $value;
	}
}

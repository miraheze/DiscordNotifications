<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'], [
		'../../extensions/CentralAuth',
		'../../extensions/Flow',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'], [
		'../../extensions/CentralAuth',
		'../../extensions/Flow',
	]
);

$cfg['suppress_issue_types'] = [
	'PhanAccessMethodInternal',
];

return $cfg;

<?php

class Example {


	/**
	 * @value=42
	 * @type=integer ololo yyy
	 */
	public $id;

	/**
	 * @numbers=12.94
	 * @field=value
	 * @foo="Hello \"World\""
	 * @foo1="Hello \\\\\" World \\\\" garbage at end of\" string"
	 * @tab="[\t]"
	 * @newline="[\n]"
	 */
	public $message;


	private $kkk;

	static private $moo;

	static protected $kfkfkf;
}


class DocComment {

	public $parameters = array();

	public function __construct($docComment) {
		if ($docComment) {
			preg_match_all('/@([a-zA-Z_][a-zA-Z0-9_]*)=(?:([^"\\s]+)|"((?:[^"\\\\]|\\\\"|\\\\\\\\|\\\\n|\\\\t)+)")/', $docComment, $matchesArr, PREG_SET_ORDER);
			foreach ($matchesArr as $matches) {
				if (isset($matches[3])) {
					// Unescape characters in quoted string
					$value = str_replace(array('\\\\', '\\"', '\\n', '\\t'), array('\\', '"', "\n", "\t"), $matches[3]);
				} else {
					$value = $matches[2];
				}
				$this->parameters[$matches[1]] = $value;
			}
		}
	}

}


$reflection = new ReflectionClass("Example");

$props = $reflection->getProperties();


foreach ($props as $prop) {
	echo "=== " . $prop->getName() . " ===\n";

	echo "Default:   " . ($prop->isDefault() ? "Yes" : "No") . "\n";
	echo "Private:   " . ($prop->isPrivate() ? "Yes" : "No") . "\n";
	echo "Protected: " . ($prop->isProtected() ? "Yes" : "No") . "\n";
	echo "Public:    " . ($prop->isPublic() ? "Yes" : "No") . "\n";
	echo "Static:    " . ($prop->isStatic() ? "Yes" : "No") . "\n";

	echo "Parameters:\n";
	$dc = new DocComment($prop->getDocComment());
	foreach ($dc->parameters as $k => $v) {
		echo "\t$k = $v\n";
	}


}

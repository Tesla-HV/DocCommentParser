<?php

class DocCommentParser {

	public $parameters = array();

	public function __construct($docComment) {
		if ($docComment) {
			preg_match_all('/@([a-zA-Z_][a-zA-Z0-9_]*)(?:=(?:([^"\\s]*)|"((?:[^"\\\\]|\\\\"|\\\\\\\\|\\\\n|\\\\t)*)"))?/', $docComment, $matchesArr, PREG_SET_ORDER);
			foreach ($matchesArr as $matches) {
				if (isset($matches[3])) {
					// Unescape characters in quoted string
					$value = str_replace(array('\\\\', '\\"', '\\n', '\\t'), array('\\', '"', "\n", "\t"), $matches[3]);
				} else if (isset($matches[2])) {
					$value = $matches[2];
				} else {
					$value = null;
				}
				$this->parameters[$matches[1]] = $value;
			}
		}
	}

	public function get($key, $defaultValue = null) {
		return array_key_exists($key, $this->parameters) ? $this->parameters[$key] : $defaultValue;
	}

	public function has($key) {
		return array_key_exists($key, $this->parameters);
	}

}

abstract class FieldSerializer {

	protected $propertyName;
	protected $tableName;
	protected $fieldName;
	protected $pkey;

	public function __construct($propertyName, $tableName, $fieldName, $pkey) {
		$this->propertyName = $propertyName;
		$this->tableName = $tableName;
		$this->fieldName = $fieldName;
		$this->pkey = $pkey;
	}

	public function getName() {
		return $this->fieldName;
	}

	public function isPKey() {
		return $this->pkey;
	}

	protected function getValue($entity) {
		$fieldName = $this->getName();
		return $entity->$fieldName;
	}

	abstract public function serialize($entity, \SetMaker $setMaker);
}

class StringSerializer extends FieldSerializer {
	public function serialize($entity, \SetMaker $setMaker) {
		$value = $this->getValue($entity);
		// TODO: real_escape_string
		$value = $value === null ? "NULL" : "'".(string) $value."'";
		$setMaker->set($this->tableName, $this->fieldName, $value);
	}
}

class IntegerSerializer extends FieldSerializer {
	public function serialize($entity, \SetMaker $setMaker) {
		$value = $this->getValue($entity);
		$value = $value === null ? "NULL" : (string) (int) $value;
		$setMaker->set($this->tableName, $this->fieldName, $value);
	}
}

class SetMaker {

	protected $aliases = array();
	protected $set = array();

	public function __construct($aliases = null) {
		if ($aliases) {
			$this->aliases = $aliases;
		}
	}

	public function set($tableName, $fieldName, $value) {
		if (isset($this->aliases[$tableName])) {
			$alias = $this->aliases[$tableName];
		} else {
			$alias = $tableName;
		}
		$this->set[] = "{$alias}.{$fieldName} = {$value}";
	}

	public function toString() {
		return implode(", ", $this->set);
	}
}

abstract class EntityManager {

	const ALL_FIELDS = 0;
	const PKEY = 1;
	const EXCEPT_PKEY = 2;

	abstract public function newEntity();
	abstract public function getTable();
	abstract public function makeSet($entity, $fields, $aliases);
}

class ReflectionBasedManager extends EntityManager {

	protected $reflectionClass;
	protected $classInfo;
	protected $entityFields;

	public function __construct(\ReflectionClass $reflectionClass) {
		$this->reflectionClass = $reflectionClass;
		$this->classInfo = new DocCommentParser($reflectionClass->getDocComment());
		$this->entityFields = array();
		foreach ($reflectionClass->getProperties() as $reflectionProperty) {
			if (!$reflectionProperty->isStatic()) {
				$entityField = $this->tryCreateFieldSerializer($reflectionProperty);
				if ($entityField) {
					$this->entityFields[] = $entityField;
				}
			}
		}
	}

	private function tryCreateFieldSerializer(\ReflectionProperty $reflectionProperty) {
		$propertyInfo = new DocCommentParser($reflectionProperty->getDocComment());
		$serializerClass = $propertyInfo->get("serializer");
		if ($serializerClass === null) {
			switch ($propertyInfo->get("type")) {
			case null:
				return null;
			case "integer":
				$serializerClass = "\\IntegerSerializer";
				break;
			case "string":
				$serializerClass = "\\StringSerializer";
				break;
			default:
				throw new \UnexpectedValueException("Invalid value '".$type."' for @type annotation");
			}
		}
		$tableName = $propertyInfo->get("table");
		if ($tableName === null) {
			$tableName = $this->classInfo->get("table");
		}
		$fieldName = $propertyInfo->get("field");
		if ($fieldName === null) {
			$fieldName = $reflectionProperty->getName();
		}
		$pkey = $propertyInfo->has("pkey");
		$fieldSerializer = new $serializerClass($reflectionProperty->getName(), $tableName, $fieldName, $pkey);
		return $fieldSerializer;
	}

	public function newEntity() {
		return $this->reflectionClass->newInstance();
	}

	public function makeSet($entity, $fields = EntityManager::EXCEPT_PKEY, $aliases = null) {
		// TODO: filtering fields, aliases
		$setMaker = new SetMaker($aliases);
		foreach ($this->entityFields as $f) {
			if (is_int($fields)) {
				if (!(
					($fields === EntityManager::PKEY && !$f->isPKey()) ||
					($fields === EntityManager::EXCEPT_PKEY && $f->isPKey())
				)) {
					$f->serialize($entity, $setMaker);
				}
			} else {
				if (in_array($f->getName(), $fields)) {
					$f->serialize($entity, $setMaker);
				}
			}
		}
		return $setMaker->toString();
	}

	public function getTable() {
		$table = $this->classInfo->get("table");
		if ($table === null) {
			throw new \UnexpectedValueException("No value specified for @table");
		}
		return $table;
	}
}

/////////////////// TEST ///////////////////

/**
 * @table=guestbook
 */
class GuestbookRecord {

	/**
	 * @type=integer
	 * @pkey
	 */
	public $id;

	/**
	 * @type=string
	 */
	public $message;

	/**
	 * @serializer=\StringSerializer
	 */
	public $title;
}

$entityManager = new ReflectionBasedManager(new ReflectionClass("GuestbookRecord"));

$ent = $entityManager->newEntity();

$ent->id = 42;
$ent->message = "Hello World!";
$ent->title = "Test";

$table = $entityManager->getTable();
$set = $entityManager->makeSet($ent);
echo "INSERT $table SET $set;\n";


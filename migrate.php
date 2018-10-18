<?php
define('CRON','CLI');
include "standalone.php";

$connection = ConnectionPool::getInstance()->getConnection();
$typesCollection = umiObjectTypesCollection::getInstance();
$fieldsCollection = umiFieldsCollection::getInstance();
$dataTypes = getDataTypes();

$enviroment = (isset($argv[2])) ? trim($argv[2]) : "dev";

$action = trim($argv[1]);


switch ($action) {
	/* создаём таблицу, где будут храниться выполненные миграции */
	case 'install':
		install();
		break;

	/* выводим список миграций */
	case 'list':
		$list = readMigrations(true);
		foreach ($list as $key => $sublist) {
			echo (!$key) ? "Новые\n" : "Применены\n";
			
			foreach($sublist as $subkey=>$sublist_item) {
				echo "$subkey: $sublist_item\n";
			}
		}
		break;

	/* запускаем невыполненные миграции или одну из списка (по номеру) */
	case 'run':
		run();
		break;

	default:
		exit("do nothing :) why not?\nCurrent enviroment is $enviroment\n");
		break;
}


/* Функция создаёт таблицу для отслеживания миграций */
function install() {
	global $connection;

	$sql = "CREATE TABLE IF NOT EXISTS migrations ( id INT NOT NULL AUTO_INCREMENT , name VARCHAR(255) NOT NULL , applied BOOLEAN, PRIMARY KEY id(id)) ENGINE = InnoDB";

	$connection->queryResult($sql);
}

/* Функция читает миграции и проверяет есть ли они в таблице */
function readMigrations($all = false) {
	global $connection;

	$dir = opendir('./migrations');
	

	while (false !== ($entry = readdir($dir))) {
		$matches = [];

		if(preg_match('/^([a-z_-]+)\.json$/', $entry, $matches)) {
			$sql = "SELECT * FROM migrations WHERE name ='{$matches[1]}'";
			$res = $connection->queryResult($sql);
			if($res->length()) {
				$data = $res->fetch();
				$result[$data['applied']][]= $data['name'];
				$res->freeResult();
			}else{
				$result[0][] = $matches[1];
			}
			
		}
	}
	
	closedir($dir);

	return ($all) ? $result : $result[0];
}


/* функция перебирает миграции и запускает */
function run() {
	$migrations = readMigrations();

	foreach ($migrations as $key=>$migration) {
			$migration_data = file_get_contents("./migrations/$migration.json");
			applyMigrations($migration_data, $migration);
	}
}


/* Функция применяет миграции из файла */
function applyMigrations($migration_data, $migration_name) {
	global $typesCollection, $enviroment, $connection, $dataTypes;
	
	$migrations = json_decode($migration_data, true);

	if($migrations === null) {
		exit("Проверьте правильность json-файла");
	}

	foreach($migrations as $migration) {
		$env = $migration['enviroment'][$enviroment];

		switch ($migration["action"]) {
			case 'create':
				// создаем тип данных
				$newTypeId = $typesCollection->addType($env['parent_id'], $env['name']);
				$newType  = $typesCollection->getType($newTypeId);

				foreach($env['groups'] as $group) {
					// создаём группу
					createGroupAndFields($group, $newType);
				}

				break;

			case 'delete':
				# code...
				$typesCollection->delType($env['id']);
				break;

			// действие по-умолчанию - обновление типа данных
			case 'update':
			default: 
				// получаем тип данных, с которым будем работать
				$type = $typesCollection->getType($env['id']);

				foreach($env as $key=>$value) {
					switch ($key) {
						case 'name':
							$type->setName($value);
							break;

						case 'is_public':
							$type->setIsPublic((bool) $value);
							break;

						default:
							continue;
							break;
					}
				}

				//далее перебираем группы
				foreach ($env['groups'] as $group) {
					switch ($group['action']) {
						case 'create':
							createGroupAndFields($group, $type);
							break;
						case 'delete':
							$type->delFieldsGroup($group['id']);
							# code...
							break;
						//обновлеие существующей группы - действие по-умолчанию.
						case 'update':
						default:
							$umi_group = $type->getFieldsGroupByName($group["name"]);

							foreach ($group as $key=>$value) {
								switch ($key) {
									case 'name':
										$umi_group->setName($group['name']);
										break;
									case 'title':
										$umi_group->setTitle($group['title']);
										break;
									
									default:
										continue;
										break;
								}

								foreach ($group['fields'] as $field) {
									switch ($field['action']) {
										case 'update':
											# code...
											foreach($field as $key=>$value) {
												$umi_field = getFieldByName($field['name'], $umi_group);

												switch ($key) {
													case 'new_name':
														$umi_field->setName($value);
														break;
													case 'title':
														$umi_field->setTitle($value);
														break;
													case 'type_id':
														$umi_field->setFieldTypeId($dataTypes[$value]);

														# code...
														break;
													
													default:
														continue;
														break;
												}
											}
											break;

										case 'delete':
											# code...
											deleteFieldByName($field['name'], $umi_group);
											break;
										
										// действие по-умолчанию - создание поля
										case 'create':
										default:
											createFieldandAttachToGroup($field,$umi_group);
											break;
									}

								}
							}

							break;
					}
				}
				break;
		}

		$sql = "INSERT INTO migrations (name, applied) VALUES ('$migration_name', '1')";
		$connection->queryResult($sql);
	}
}

/* функция получает типы данных полей */
function getDataTypes() {
	$res = [];
	$fieldTypesCollection = umiFieldTypesCollection::getInstance();
	$fieldTypesList = $fieldTypesCollection->getFieldTypesList();
	foreach($fieldTypesList as $fieldType) {
	  $res[$fieldType->getDataType()]=$fieldType->getId();
	}
	return $res;
}

function createGroupAndFields($group, $type) {
	// создаём группу
	$newGroupId = $type->addFieldsGroup($group['name'],$group['title']);
	$newGroup = $type->getFieldsGroup($newGroupId);

	foreach($group['fields'] as $field) {
		createFieldandAttachToGroup($field, $newGroup);
	}
}

function createFieldandAttachToGroup($field, $group) {
	global $fieldsCollection, $dataTypes;
	// создаём поля
	$newFieldId = $fieldsCollection->addField($field['name'],$field['title'],$dataTypes[$field['type_id']]);

	// прикрепляем их к группе
	$group->attachField($newFieldId);
}

function deleteFieldByName($field_name, $group) {
	global $fieldsCollection;

	$fields = $group->getFields();
	foreach ($fields as $field) {
		if($field->getName() == $field_name) {
			$fieldsCollection->delField( $field->getId() );
		}
	}
}

function getFieldByName($field_name, $group) {
	$fields = $group->getFields();
	foreach ($fields as $field) {
		if($field->getName() == $field_name) {
			return $field;
		}
	}
}

<?php
define('CRON','CLI');
include "standalone.php";

$typesCollection = umiObjectTypesCollection::getInstance();
$fieldsCollection = umiFieldsCollection::getInstance();
$dataTypes = getDataTypes();

$enviroment = "dev";
$migration_data = file_get_contents('./migrations/update_type.json');
$migrations = json_decode($migration_data, true);

if($migration === null) {
	exit("Проверьте правильность json-файла");
}

foreach($migrations as $migration) {
	$env = $migration['enviroment'][$enviroment];

	switch ($migration["action"]) {
		case 'create':
			echo "creating data type ".$env['name']."\n";
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
			echo "update";
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

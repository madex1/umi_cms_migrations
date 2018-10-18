# Миграции для UMI.CMS
Утилита командной строки, которая добавляет функционал миграций в UMI.CMS.

## Команды

1. Создаёт таблицу migrations, куда записывает выполненные миграции
<pre>php migrate.php install</pre>

2. Выводит список миграций выполненных и невыполненных
<pre>php migrate.php list</pre>

3. Выводит список доступны типов полей
<pre>php migrate.php types</pre>

4. Запускает список миграций
<pre>php migrate.php run dev</pre>
где dev - среда.

## Миграции
Миграции представлены в виде json-файлов в папке /migrations
JSON-файл преставляет массив объектов. 1 объект - 1 миграция для 1 типа данных.

### Пример json-файла
Внимание! JSON-файлы не поддерживат комментарии.

<pre>
[
	{
		"action": "create", // action - основное действие, относящее к типу данных, может быть create, update, delete 
		"enviroment": { // enviroment - среда. Для каждой среды можно задать свои id-типов и имена полей
			"dev": { // dev - среда разработки
				"name": "Новый тип данных", // имя
				"parent_id": 312, // родительский тип
        		"is_public": 1, // является справочником
				"groups": [
					{
						"action": "create", // если не указано, то будет update
            			"name": "group_1",
						"title": "Группа полей 1",
						"fields": [
							{
                				"action": "create", // если не указано, то будет create (не update, как везде!)
								"name": "field_1",
								"title": "Поле 1",
								"type_id": "string" // тип поля (посмотреть все можно командой php migrate.php types)
							},
							{
                				"action": "create",
								"name": "field_2",
								"title": "Поле 2",
								"type_id": "string"
							}
						]
					},
					{
						"action": "create",
            			"name": "group_2",
						"title": "Группа полей 2",
						"fields": [
							{
								"name": "field_1",
								"title": "Поле 1",
								"type_id": "string"
							},
							{
								"name": "field_2",
								"title": "Поле 2",
								"type_id": "string"
							}
						]
					}
					
				]
			},
			"production": {
				"parent_id": 123
         		// для другой среды надо полностью продублировать все поля
			}
		}
	},
	{
		"action": "update",
		"enviroment": {
			"dev": {
				"id": 123, // id типа данных
				"name": "Новое имя типа данных",
				"groups": [
					{
						"name": "group_1",
						"title": "Группа полей 1",
						"fields": [
							{
								"action": "create",
								"name": "field_3",
								"title": "Поле 3",
								"type_id": "string"
							},
							{
								"action": "create",
								"name": "field_4",
								"title": "Поле 4",
								"type_id": "string"
							}
						]
					},
					{
						"action": "update",
						"name": "group_2",
						"title": "Группа полей 2",
						"fields": [
							{
								"action": "update",
								"name": "field_1",
								"new_name": "field_2", // Так как пое ищется по имени в группе, то новое имя указывается отдельным параметром
								"title": "Поле 1",
								"type_id": "string"
							},
							{
								"action": "delete",
								"name": "field_2",
								"title": "Поле 2",
								"type_id": "string"
							}
						]
					}
					
				]
			},
			"production": {
				"id": 123
        		// для другой среды нужно продублировать все данные с её id
			}
		}
	},
	{
    	// пример удаления типа данных. Для удаления типа данных достаточно его id
		"action": "delete",
		"enviroment": {
			"dev": {
				"id": 123
			},
			"production": {
				"id": 124
			}
		}
	}
]
</pre>

<?php

/**
 * Путь до целевой директории
 */
$path = './datafiles';

/**
 * Получаем список файлов в целевой диреуктории
 */
$dir = scandir($path);

/**
 * Фильтруем полученный список файлов по регулярке из задания
 */
$res = array_filter($dir, static function($file) {
    return preg_match('/^[a-zA-Z0-9]+\.ixt$/', $file);
});

/**
 * Сортируем отфильтрованный список файлов
 */
sort($res);

/**
 * Для каждого файла выводим на новой строке имя
 */
foreach ($res as $file) {
    echo $file . PHP_EOL;
}
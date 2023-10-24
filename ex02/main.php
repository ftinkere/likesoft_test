<?php

/**
 * Получаем строку с html необходимой страницы в переменную $html
 */
$curl_handle = curl_init();
curl_setopt($curl_handle, CURLOPT_URL,'https://www.bills.ru');
curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 2);
curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($curl_handle, CURLOPT_USERAGENT, 'php');
$html = curl_exec($curl_handle);
curl_close($curl_handle);

/**
 * Создаём объект DOM дерева по полученному html
 */
$dom = new DOMDocument();
$dom->loadHTML($html);

/**
 * Создаём объект для поиска в DOM по XPath
 */
$xpath = new DOMXPath($dom);

/**
 * Находим все необходимые строки событий на долговом рынке
 * @var DOMNodeList $rows
 */
$rows = $xpath->evaluate('(//div[contains(text(), \'на долговом рынке\')]/..//table[@id="bizon_api_news_list"])[1]//tr[@class="bizon_api_news_row"]');

/**
 * Вытаскиваем с элементов строк необходимые нам данные и сохраняем в массив
 */
$res = [];
foreach ($rows as $row) {
    /** @var DOMElement $row */

    $date = trim($row->firstElementChild->textContent);
    $title = $row->firstElementChild->nextElementSibling->firstElementChild->textContent;
    $url = $row->firstElementChild->nextElementSibling->firstElementChild->getAttribute('href');

    $res[] = compact('date', 'title', 'url');
}

/**
 * Открываем соединение с базой данных, которую надо запустить из docker compose
 */
$dbh = new PDO('mysql:dbname=tested;host=127.0.0.1;port=3307', 'user', 'password');

/**
 * Создаём таблицу bills_ru_events, если она ещё не создана
 */
$dbh->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS `bills_ru_events` (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        date DATETIME NOT NULL,
        title VARCHAR(230) NOT NULL,
        url VARCHAR(240) UNIQUE NOT NULL
    )
    SQL
);

/**
 * Переводит дату из формата '01 янв 2020' в формат '2020-01-01 00:00:00', чтобы сохранить так в базу данных
 * @param string $date Дата в исходном формате сайта
 * @return string Дата в формате пригодном для сохранения в БД
 */
function to_db_date($date) {
    [$day, $month, $year] = explode(' ', $date);

    $month = match ($month) {
        'янв' => '01',
        'фев' => '02',
        'мар' => '03',
        'май' => '04',
        'апр' => '05',
        'июн' => '06',
        'июл' => '07',
        'авг' => '08',
        'сен' => '09',
        'окт' => '10',
        'ноя' => '11',
        'дек' => '12',
    };

    return (new DateTime(implode('.', [$day, $month, $year])))?->format('Y-m-d H:i:s');
}

/**
 * Подготавливаем запрос на вставку данных в базу данных
 */
$sth = $dbh->prepare(<<<SQL
    INSERT INTO `bills_ru_events` (date, title, url)
    VALUES (:date, :title, :url)
    SQL
);

/**
 * Для каждой найденной записи сохраняем данные в базу данных
 */
foreach ($res as $item) {
    $item['date'] = to_db_date($item['date']);
    $sth->execute($item);
}
# BaksDev Product Stocks

![Version](https://img.shields.io/badge/version-6.2.0-blue) ![php 8.1+](https://img.shields.io/badge/php-min%208.1-red.svg)

Модуль складского учета продукции

## Установка

``` bash
$ composer require baks-dev/products-stocks
```

## Дополнительно

Установка файловых ресурсов в публичную директорию (javascript, css, image ...):

``` bash
$ php bin/console baks:assets:install
```

Роли администартора с помощью Fixtures

``` bash
$ php bin/console doctrine:fixtures:load --append
```

Изменения в схеме базы данных с помощью миграции

``` bash
$ php bin/console doctrine:migrations:diff

$ php bin/console doctrine:migrations:migrate
```

## Журнал изменений ![Changelog](https://img.shields.io/badge/changelog-yellow)

О том, что изменилось за последнее время, обратитесь к [CHANGELOG](CHANGELOG.md) за дополнительной информацией.

## Лицензия ![License](https://img.shields.io/badge/MIT-green)

The MIT License (MIT). Обратитесь к [Файлу лицензии](LICENSE.md) за дополнительной информацией.



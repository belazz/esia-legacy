
# Единая система идентификации и аутентификации (ЕСИА) OpenId 

[![Build Status](https://travis-ci.org/fr05t1k/esia.svg?branch=master)](https://travis-ci.org/fr05t1k/esia)

# Описание
Компонент для авторизации на портале "Госуслуги"

Этот репозиторий предназначен для проектов с версией php >= 5.5

Для php >= 7.0 используй [этот](https://github.com/fr05t1k/esia-legacy/) 

Основано на [этом компоненте](https://github.com/fr05t1k/esia/). 

В этом форке был добавлена возможность обновлять access_token с помощью refresh_token'ов
Также при успешной авторизации значения refresh_token и expires_in сохраняются в память - есть возможность сохранить их в БД/сессию и тд 

# Composer

[Composer](https://getcomposer.org/download/)

В composer.json добавить строчки:
```
      "repositories": [{
          "type": "vcs",
          "url": "http://github.com/belazz/esia"
      }],
      
      "require": {
              ...
              "fr05t1k/esia": "dev-master",
              ...
      },
```

В данном случае master - имя нужной нам ветки форка, а префикс dev- -- обязателен для того, чтобы composer понял что откуда пулить. 
Для тех кто хочет форкнуть - САМУ ВЕТКУ ФОРКА не обязательно называть с префиксом dev- 

# Как использовать 

Пример получения ссылки для авторизации
```php
<?php 
$config = new \Esia\Config([
  'clientId' => 'INSP03211',
  'redirectUrl' => 'http://my-site.com/response.php',
  'portalUrl' => 'https://esia-portal1.test.gosuslugi.ru/',
  'privateKeyPath' => 'my-site.com.pem',
  'privateKeyPassword' => 'my-site.com',
  'certPath' => 'my-site.com.pem',
  'tmpPath' => 'tmp',
  'scope' => ['fullname', 'birthdate'],
]);
$esia = new \Esia\OpenId($config);
?>

<a href="<?=$esia->buildUrl()?>">Войти через портал госуслуги</a>
```

После редиректа на ваш `redirectUrl` вы получите в `$_GET['code']` код для получения токена

Пример получения токена и информации о пользователе

```

$esia = new \Esia\OpenId($config);

// Вы можете использовать токен в дальнейшем вместе с oid 
$token = $esia->getToken($_GET['code']);

$personInfo = $esia->getPersonInfo();
$addressInfo = $esia->getAddressInfo();
$contactInfo = $esia->getContactInfo();
$documentInfo = $esia->getDocInfo();

```
## Обновление токена
```
    $refreshToken = $config->getRefreshToken(); // для получения сохраненного при реквесте refresh_token'a
    $newAccessToken = $esia->refreshToken($refreshToken); // получение нового access_token'a с помощью refresh_token'a
```
## Получение значения expires_in
Также возвращаемое кол-во секунд expires_in сохраняется в Config $config объект, доступно через:
```
    $config->getTokenExpiresIn();    
```  
Проверку актуальный ли ещё токен нужно реализовать самому. Например, с использованием Carbon:
```
    return Carbon::now() < Carbon::now()->addSeconds($config->getTokenExpiresIn());    
```
expires_in должен быть сохранен в каком-либо хранилище (также как и access_token, refresh_token и oid), соотв. при сравнении забираем его оттуда
# Конфиг

`token` - access_token 

`refreshToken` - refresh_token (нужен для запроса нового access_token без участия пользователя)

`tokenExpiresIn` - через сколько действие access_token'a закончится, указывается в секундах

`clientId` - ID вашего приложения.

`redirectUrl` - URL куда будет перенаправлен ответ с кодом.

`portalUrl` - по умолчанию: `https://esia-portal1.test.gosuslugi.ru/`. Домен портала для авторизация (только домен).

`codeUrlPath` - по умолчанию: `aas/oauth2/ac`. URL для получения кода.

`tokenUrlPath` - по умолчанию: `aas/oauth2/te`. URL для получение токена.

`scope` - по умолчанию: `fullname birthdate gender email mobile id_doc snils inn`. Запрашиваемые права у пользователя.

`privateKeyPath` - путь до приватного ключа.

`privateKeyPassword` - пароль от приватного ключа.

`certPath` - путь до сертификата.

`tmpPath` - путь до дериктории где будет проходить подпись (должна быть доступна для записи).

# Токен и oid

Токен - jwt токен которые вы получаете от ЕСИА для дальнейшего взаимодействия

oid - уникальный идентификатор владельца токена

## Как получить oid?
Если 2 способа:
1. oid содержится в jwt токене, расшифровав его
2. После получения токена oid сохраняется в config и получить можно так 
```php
$esia->getConfig()->getOid();
```

## Переиспользование Токена

Дополнительно укажите токен и идентификатор в конфиге
```php
$config->setToken($jwt);
$config->setOid($oid);
```

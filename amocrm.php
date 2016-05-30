<?php

header('Content-Type: text/html;charset=utf-8');

# Принимаем данные. Очищаем их от мусора.
foreach ($_POST as $key => $var) {
    if  (!empty($var)) {
        $_POST[$key] = addslashes(strip_tags(trim($var)));
    }
}
#-------------------------------------------------------------------
# Работаем с AMOCRM
include_once('../amocrm/amoapi.inc.php');

// Подготавливаем телефон для AMOCRM
$_POST['phone'] = isset($_POST['phone']) ? preg_replace("/ +/", "", trim($_POST['phone'])) : 'undefined';
$_POST['phone'] = preg_replace("/-+/", "", trim($_POST['phone']));
$_POST['phone'] = str_replace("(", "", trim($_POST['phone']));
$_POST['phone'] = str_replace(")", "", trim($_POST['phone']));
$_POST['name'] = isset($_POST['name']) ? trim($_POST['name']) : 'noname';
$_POST['utm_source'] = isset($_POST['utm_s']) ? trim($_POST['utm_s']) : 'no_utm_source';
$_POST['utm_medium'] = isset($_POST['utm_m']) ? trim($_POST['utm_m']) : 'no_utm_medium';
$_POST['utm_campaign'] = isset($_POST['utm_c']) ? trim($_POST['utm_c']) : 'no_utm_campaign';
$_POST['utm_term'] = isset($_POST['utm_t']) ? trim($_POST['utm_t']) : 'no_utm_term';
$_POST['ga'] = isset($_POST['ga']) ? trim($_POST['ga']) : 'no_ga';

$_POST['formname'] = isset($_POST['formname']) ? trim($_POST['formname']) : 'subject undefined';

// подключаемся
$amo = new AmoRestApi('subdomain', 'login', 'hash');

// добавляем сделку
$leads['add'] = array(
    array(
        'name' => $_POST['formname'],
        'custom_fields' => array(
            array(
                'id' => 578992,
                'values' => array(
                    array(
                        'value' => $_POST['utm_source']
                    )
                )
            ),
            array(
                'id' => 578994,
                'values' => array(
                    array(
                        'value' => $_POST['utm_medium']
                    )
                )
            ),
            array(
                'id' => 578996,
                'values' => array(
                    array(
                        'value' => $_POST['utm_campaign']
                    )
                )
            ),
            array(
                'id' => 578998,
                'values' => array(
                    array(
                        'value' => $_POST['utm_term']
                    )
                )
            ),
            array(
                'id' => 579000,
                'values' => array(
                    array(
                        'value' => $_POST['ga']
                    )
                )
            )
        )
    )
);
$request = $amo -> setLeads($leads);
$idLeads = $request[0]['id'];

// проверяем, есть ли в системе с таким же телефоном
$newUser = FALSE;
$user = $amo -> getContactsList(null, null, null, $_POST['phone']);
if (count($user) > 0) { // пользователь существует
    $contacts['update'] = array(
        array(
            'id' => $user['contacts'][0]['id'],
            'last_modified' => time(),
            'linked_leads_id' => array($idLeads)
        )
    );
} else {
    // добавляем пользователя
    $contacts['add'] = array(
        array(
            'name' => $_POST['name'],
            'date_create' => time(),
            'linked_leads_id' => array($idLeads),
            'custom_fields' => array(
                #phone
                array(
                    'id' => 249185,
                    'values' => array(
                        array(
                            'value' => $_POST['phone'],
                            'enum' => 'MOB' #Мобильный
                        )
                    )
                )
            )
        )
    );

    $newUser = TRUE;
}
$addUser = $amo -> setContacts($contacts);

if ($newUser === TRUE) {
    // Дублируем в примечание номер телефона и имя
    $notes['add'] = array(
        array(
            'element_id' => $addUser['contacts']['add'][0]['id'],
            'element_type' => 1,
            'note_type' => 4,
            'text' => 'Имя: ' . $_POST['name'] . ', Телефон: ' . $_POST['phone'],
            'responsible_user_id' => 469183
        )
    );
    $amo -> setNotes($notes);
}

// Добавляем задание
$tasks['add'] = array(
    array(
        'element_id' => $idLeads,
        'element_type' => 2,
        'task_type' => 1,
        'text' => 'Клиент заказал звонок',
        'complete_till' => time() + 1800 # 30 минут от даты создания задания
    )
);
$amo -> setTasks($tasks);


#-------------------------------------------------------------------
# Отправляем письмо
//$to =  'email@mail.com'; // электронный адрес на который отправляем почту
$to =  'email@mail.com';

$subject = isset($_POST['formname']) ? $_POST['formname'] : 'subject undefined'; // тема письма
$message = 'Сообщение.' . "<br />";
foreach ($_POST as $key => $var) {
    if (!empty($var) && $key != 'formname') {
        $message .= $var . "<br />";
    }
}
$message .= '<br>Письмо отправлено автоматически и не требует ответа';
$headers  = "Content-type: text/html; charset=utf-8 \r\n";
$headers .= "From: Алексей <robot@".$_SERVER['SERVER_NAME'].">\r\n";

mail($to, $subject, $message, $headers);

?>

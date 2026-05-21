<?php
// БЛОКИРОВКА ПОЛЯ UF_CRM_4_SITE (ID 114)
$fieldId = 114;

// Получаем текущее состояние поля
$field = \CUserTypeEntity::GetByID($fieldId);
if ($field) {
    // Проверяем, не заблокировано ли поле уже
    if ($field['EDIT_IN_LIST'] !== 'N') {
        $userField = new \CUserTypeEntity();
        $result = $userField->Update($fieldId, [
            'EDIT_IN_LIST' => 'N'
        ]);

        // Логи результата
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/test_init.txt', 
            date('Y-m-d H:i:s') . " - Результат блокировки: " . ($result ? 'УСПЕШНО' : 'ОШИБКА') . "\n", 
            FILE_APPEND);
    } else {
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/test_init.txt', 
            date('Y-m-d H:i:s') . " - Поле уже заблокировано\n", 
            FILE_APPEND);
    }
} else {
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/test_init.txt', 
        date('Y-m-d H:i:s') . " - Поле с ID $fieldId не найдено\n", 
        FILE_APPEND);
}


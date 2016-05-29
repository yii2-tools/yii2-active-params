<?php

use yii\db\Migration;
use yii\tools\params\models\ActiveParam;

class v160207_084448_create_params_table extends Migration
{
    public function up()
    {
        $this->createTable(ActiveParam::tableName(), [
            'name' => $this->string(255)->notNull(),
            'value' => $this->string(255),
            'category' => $this->string(255),
            'created_at' => $this->integer(11) . ' unsigned NOT NULL',
            'updated_at' => $this->integer(11) . ' unsigned NOT NULL',
            'PRIMARY KEY ([[name]], [[category]])',
            'KEY `index_params_category` ([[category]])',
            'KEY `index_params_category_updated_at` ([[category]], [[updated_at]])',
        ], 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB');
    }

    public function down()
    {
        $this->dropTable(ActiveParam::tableName());
    }
}

<?php

namespace app\model;

use Exception;
use support\Model;

use FrameX\Auth\Interfaces\IdentityRepositoryInterface;
use FrameX\Auth\Interfaces\IdentityInterface;


class User extends Model implements IdentityRepositoryInterface, IdentityInterface
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'Users';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    public function tokens()
    {
        $this->hasMany(Token::class);
    }

    /**
     * Поиск пользователя по ID
     */
    public function findIdentity($data): ?IdentityInterface
    {
        return static::find($data['user_id'] ?? $data);
    }

    /**
     * Получить ID личности
     * @return string|null
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Обновить личность 
     * @return $this
     */
    public function refreshIdentity()
    {
        return $this->refresh();
    }


    public function cryptPass($raw)
    {
        $step1 = md5('1324354657687980!@#$%^&*' . $raw . '*&^%$#@!1324354657687980');
        $step2 = md5('*&^%$#@!1324354657687980' . $step1 . '1324354657687980!@#$%^&*');
        $step3 = md5('!@#$%^&*1324354657687980' . $step2 . '1324354657687980*&^%$#@!');
        return $step3;
    }

    public function diffPassword($password)
    {
        return !empty($this->password) && $this->password == $this->cryptPass($password);
    }

    public function generateToken()
    {
        // Генерация
        $tokens = JWT()::create(
            payload: ['aud' => 'Вебпрактик'],
            data: ['user_id' => $this->id]
        );
        if (!$tokens) {
            throw new Exception("Ошибка создания токена", 500);
        }

        // Данные
        $data = [
            'user_id' => $this->id,
            'time' => time(),
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token']
        ];

        // Запись
        $query = Token::create($data);
        // $query = static::createEntity($data);
        if (!$query) {
            throw new Exception("Ошибка записи токена", 500);
        }

        return $query;
    }
}

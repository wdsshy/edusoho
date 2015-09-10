<?php

namespace Topxia\Service\User\Dao\Impl;

use Topxia\Service\Common\BaseDao;
use Topxia\Service\User\Dao\UserDao;
use Topxia\Common\DaoException;
use PDO;

class UserDaoImpl extends BaseDao implements UserDao
{
    protected $table = 'user';

    public function getUser($id, $lock = false)
    {
        $that = $this;

        return $this->fetchCached("id:{$id}", $id, $lock, function ($id, $lock) use ($that) {
            $sql = "SELECT * FROM {$that->getTable()} WHERE id = ? LIMIT 1";
            if ($lock) {
                $sql .= " FOR UPDATE";
            }
            return $that->getConnection()->fetchAssoc($sql, array($id)) ? : null;
        });
    }

    public function findUserByEmail($email)
    {
        $that = $this;

        return $this->fetchCached("email:{$email}", $email, function ($email) use ($that) {
            $sql = "SELECT * FROM {$that->getTable()} WHERE email = ? LIMIT 1";
            return $that->getConnection()->fetchAssoc($sql, array($email));
        });
    }

    public function findUserByNickname($nickname)
    {
        $that = $this;

        return $this->fetchCached("nickname:{$nickname}", $nickname, function ($nickname) use ($that) {
            $sql = "SELECT * FROM {$that->getTable()} WHERE nickname = ? LIMIT 1";
            return $that->getConnection()->fetchAssoc($sql, array($nickname));
        });
    }

    public function findUserByVerifiedMobile($mobile)
    {
        $that = $this;

        return $this->fetchCached("mobile:{$mobile}", $mobile, function ($mobile) use ($that) {
            $sql = "SELECT * FROM {$that->getTable()} WHERE verifiedMobile = ? LIMIT 1";
            return $that->getConnection()->fetchAssoc($sql, array($mobile));
        });
    }

    public function findUsersByNicknames(array $nicknames)
    {
        if(empty($nicknames)) { 
            return array(); 
        }

        $marks = str_repeat('?,', count($nicknames) - 1) . '?';
        $sql ="SELECT * FROM {$this->table} WHERE nickname IN ({$marks});";
        
        return $this->getConnection()->fetchAll($sql, $nicknames);
    }

    public function findUsersByIds(array $ids)
    {
        if(empty($ids)){
            return array();
        }
        $marks = str_repeat('?,', count($ids) - 1) . '?';
        $sql ="SELECT * FROM {$this->table} WHERE id IN ({$marks});";
        
        return $this->getConnection()->fetchAll($sql, $ids);
    }

    public function searchUsers($conditions, $orderBy, $start, $limit)
    {
        $this->filterStartLimit($start, $limit);
        $builder = $this->createUserQueryBuilder($conditions)
            ->select('*')
            ->orderBy($orderBy[0], $orderBy[1])
            ->setFirstResult($start)
            ->setMaxResults($limit);
        return $builder->execute()->fetchAll() ? : array();
    }

    public function searchUserCount($conditions)
    {
        $builder = $this->createUserQueryBuilder($conditions)
            ->select('COUNT(id)');
        return $builder->execute()->fetchColumn(0);
    }

    protected function createUserQueryBuilder($conditions)
    {
        $conditions = array_filter($conditions,function($v){
            if($v === 0){
                return true;
            }
                
            if(empty($v)){
                return false;
            }
            return true;
        });
        if (isset($conditions['roles'])) {
            $conditions['roles'] = "%{$conditions['roles']}%";
        }

        if (isset($conditions['role'])) {
            $conditions['role'] = "|{$conditions['role']}|";
        }

        if(isset($conditions['keywordType']) && isset($conditions['keyword'])) {
            $conditions[$conditions['keywordType']]=$conditions['keyword'];
            unset($conditions['keywordType']);
            unset($conditions['keyword']);
        }

        if (isset($conditions['keywordUserType'])) {
            $conditions['type'] = "%{$conditions['keywordUserType']}%";
            unset($conditions['keywordUserType']);
        }

        if (isset($conditions['nickname'])) {
            $conditions['nickname'] = "%{$conditions['nickname']}%";
        }

        return  $this->createDynamicQueryBuilder($conditions)
            ->from($this->table, 'user')
            ->andWhere('promoted = :promoted')
            ->andWhere('roles LIKE :roles')
            ->andWhere('roles = :role')
            ->andWhere('UPPER(nickname) LIKE :nickname')
            ->andWhere('loginIp = :loginIp')
            ->andWhere('createdIp = :createdIp')
            ->andWhere('approvalStatus = :approvalStatus')
            ->andWhere('email = :email')
            ->andWhere('level = :level')
            ->andWhere('createdTime >= :startTime')
            ->andWhere('createdTime <= :endTime')
            ->andWhere('locked = :locked')
            ->andWhere('level >= :greatLevel')
            ->andWhere('verifiedMobile = :verifiedMobile')
            ->andWhere('type LIKE :type')
            ->andWhere('id NOT IN ( :excludeIds )');
    }

    public function addUser($user)
    {
        $affected = $this->getConnection()->insert($this->table, $user);
        $this->clearCached();
        if ($affected <= 0) {
            throw $this->createDaoException('Insert user error.');
        }
        return $this->getUser($this->getConnection()->lastInsertId());
    }

    public function updateUser($id, $fields)
    {
        $this->getConnection()->update($this->table, $fields, array('id' => $id));
        $this->clearCached();
        return $this->getUser($id);
    }

    public function waveCounterById($id, $name, $number)
    {
        $names = array('newMessageNum', 'newNotificationNum');
        if (!in_array($name, $names)) {
            throw $this->createDaoException('counter name error');
        }
        $sql = "UPDATE {$this->table} SET {$name} = {$name} + ? WHERE id = ? LIMIT 1";

        $result = $this->getConnection()->executeQuery($sql, array($number, $id));
        $this->clearCached();
        return $result;
    }

    public function clearCounterById($id, $name)
    {
        $names = array('newMessageNum', 'newNotificationNum');
        if (!in_array($name, $names)) {
            throw $this->createDaoException('counter name error');
        }
        $sql = "UPDATE {$this->table} SET {$name} = 0 WHERE id = ? LIMIT 1";
        $result = $this->getConnection()->executeQuery($sql, array($id));
        $this->clearCached();
        return $result;
    }

    public function analysisRegisterDataByTime($startTime,$endTime)
    {
        $sql="SELECT count(id) as count, from_unixtime(createdTime,'%Y-%m-%d') as date FROM `{$this->table}` WHERE`createdTime`>=? AND `createdTime`<=? group by from_unixtime(`createdTime`,'%Y-%m-%d') order by date ASC ";
        return $this->getConnection()->fetchAll($sql, array($startTime, $endTime));
    }

    public function analysisUserSumByTime($endTime)
    {
         $sql="select date, count(*) as count from (SELECT from_unixtime(o.createdTime,'%Y-%m-%d') as date from user o where o.createdTime<=? ) dates group by dates.date order by date desc";
         return $this->getConnection()->fetchAll($sql, array($endTime));
    }

    public function findUsersCountByLessThanCreatedTime($endTime)
    {
        $sql="SELECT count(id) as count FROM `{$this->table}` WHERE  `createdTime`<=?  ";
        return $this->getConnection()->fetchColumn($sql, array($endTime));
    }

}
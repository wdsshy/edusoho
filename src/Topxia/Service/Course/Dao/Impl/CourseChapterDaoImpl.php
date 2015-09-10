<?php

namespace Topxia\Service\Course\Dao\Impl;

use Topxia\Service\Common\BaseDao;
use Topxia\Service\Course\Dao\CourseChapterDao;

class CourseChapterDaoImpl extends BaseDao implements CourseChapterDao
{
    protected $table = 'course_chapter';

    public function getChapter($id)
    {
        $that = $this;

        return $this->fetchCached("id:{$id}", $id, function ($id) use ($that) {
            $sql = "SELECT * FROM {$that->getTable()} WHERE id = ? LIMIT 1";
            return $that->getConnection()->fetchAssoc($sql, array($id)) ? : null;
        });
    }

    public function addChapter(array $chapter)
    {
        $affected = $this->getConnection()->insert($this->table, $chapter);
        $this->clearCached();
        if ($affected <= 0) {
            throw $this->createDaoException('Insert course chapter error.');
        }
        return $this->getChapter($this->getConnection()->lastInsertId());
    }

    public function findChaptersByCourseId($courseId)
    {
        $that = $this;

        return $this->fetchCached("courseId:{$courseId}", $courseId, function ($courseId) use ($that) {
            $sql = "SELECT * FROM {$that->getTable()} WHERE courseId = ? ORDER BY createdTime ASC";
            return $that->getConnection()->fetchAll($sql, array($courseId));
        });
    }

    public function getChapterCountByCourseIdAndType($courseId, $type)
    {
        $that = $this;

        return $this->fetchCached("courseId:{$courseId}:type:{$type}:count", $courseId, $type, function ($courseId, $type) use ($that) {
            $sql = "SELECT COUNT(*) FROM {$that->getTable()} WHERE  courseId = ? AND type = ?";
            return $that->getConnection()->fetchColumn($sql, array($courseId, $type));
        });
    }

    public function getChapterCountByCourseIdAndTypeAndParentId($courseId, $type, $parentId)
    {
        $that = $this;

        return $this->fetchCached("courseId:{$courseId}:type:{$type}:parentId:{$parentId}:count", $courseId, $type, $parentId, function ($courseId, $type, $parentId) use ($that) {
            $sql = "SELECT COUNT(*) FROM {$that->getTable()} WHERE  courseId = ? AND type = ? AND parentId = ?";
            return $that->getConnection()->fetchColumn($sql, array($courseId, $type, $parentId));
        });
    }

    public function getLastChapterByCourseIdAndType($courseId, $type)
    {
        $that = $this;

        return $this->fetchCached("courseId:{$courseId}:type:{$type}", $courseId, $type, function ($courseId, $type) use ($that) {
            $sql = "SELECT * FROM {$that->getTable()} WHERE  courseId = ? AND type = ? ORDER BY seq DESC LIMIT 1";
            return $that->getConnection()->fetchAssoc($sql, array($courseId, $type)) ? : null;
        });
    }

    public function getLastChapterByCourseId($courseId)
    {
        $that = $this;

        return $this->fetchCached("courseId:{$courseId}:last", $courseId, function ($courseId) use ($that) {
            $sql = "SELECT * FROM {$that->getTable()} WHERE  courseId = ? ORDER BY seq DESC LIMIT 1";
            return $that->getConnection()->fetchAssoc($sql, array($courseId)) ? : null;
        });
    }

    public function getChapterMaxSeqByCourseId($courseId)
    {
        $sql = "SELECT MAX(seq) FROM {$this->table} WHERE  courseId = ?";
        return $this->getConnection()->fetchColumn($sql, array($courseId));
    }

    public function updateChapter($id, array $chapter)
    {
        $this->getConnection()->update($this->table, $chapter, array('id' => $id));
        $this->clearCached();
        return $this->getChapter($id);
    }

    public function deleteChapter($id)
    {
        $result = $this->getConnection()->delete($this->table, array('id' => $id));
        $this->clearCached();
        return $result;
    }

    public function deleteChaptersByCourseId($courseId)
    {
        $sql = "DELETE FROM {$this->table} WHERE courseId = ?";
        $result = $this->getConnection()->executeUpdate($sql, array($courseId));
        $this->clearCached();
        return $result;
    }

    public function findChaptersByChapterIdAndLockedCourseIds($pId, $courseIds)
    {
       if(empty($courseIds)){
            return array();
        }
       
        $marks = str_repeat('?,', count($courseIds) - 1) . '?';
       
        $parmaters = array_merge(array($pId), $courseIds);

        $sql ="SELECT * FROM {$this->table} WHERE pId= ? AND courseId IN ({$marks})";
        
        return $this->getConnection()->fetchAll($sql, $parmaters) ? : array();
    }

}
<?php
/**
 * Configuration Controller
 * 
 * Manage system configuration (priorities, statuses, topics)
 */

require_once __DIR__ . '/../database/Database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/Auth.php';

class ConfigController
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * GET /api/config/priorities
     */
    public function getPriorities()
    {
        Auth::authenticate();

        $priorities = $this->db->query(
            "SELECT * FROM priorities ORDER BY order_index"
        );

        foreach ($priorities as &$priority) {
            $priority['id'] = (int) $priority['id'];
            $priority['order_index'] = (int) $priority['order_index'];
        }

        Response::success($priorities);
    }

    /**
     * GET /api/config/statuses
     */
    public function getStatuses()
    {
        Auth::authenticate();

        $statuses = $this->db->query(
            "SELECT * FROM statuses ORDER BY order_index"
        );

        foreach ($statuses as &$status) {
            $status['id'] = (int) $status['id'];
            $status['order_index'] = (int) $status['order_index'];
        }

        Response::success($statuses);
    }

    /**
     * GET /api/config/main-topics
     */
    public function getMainTopics()
    {
        Auth::authenticate();

        $topics = $this->db->query(
            "SELECT * FROM main_topics ORDER BY name"
        );

        foreach ($topics as &$topic) {
            $topic['id'] = (int) $topic['id'];
        }

        Response::success($topics);
    }

    /**
     * GET /api/config/sub-topics
     */
    public function getSubTopics()
    {
        Auth::authenticate();

        $mainTopicId = $_GET['main_topic_id'] ?? null;

        if ($mainTopicId) {
            $subTopics = $this->db->query(
                "SELECT st.*, mt.name as main_topic_name
                 FROM sub_topics st
                 JOIN main_topics mt ON st.main_topic_id = mt.id
                 WHERE st.main_topic_id = ?
                 ORDER BY st.name",
                [$mainTopicId]
            );
        } else {
            $subTopics = $this->db->query(
                "SELECT st.*, mt.name as main_topic_name
                 FROM sub_topics st
                 JOIN main_topics mt ON st.main_topic_id = mt.id
                 ORDER BY mt.name, st.name"
            );
        }

        foreach ($subTopics as &$topic) {
            $topic['id'] = (int) $topic['id'];
            $topic['main_topic_id'] = (int) $topic['main_topic_id'];
        }

        Response::success($subTopics);
    }

    /**
     * POST /api/config/priorities
     */
    public function createPriority()
    {
        Auth::authenticate();
        Auth::requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true);

        $validator = new Validator();
        $validator->required('name', $data['name'] ?? null);

        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }

        try {
            $this->db->execute(
                "INSERT INTO priorities (name, color, order_index) VALUES (?, ?, ?)",
                [
                    $data['name'],
                    $data['color'] ?? '#6B7280',
                    $data['order_index'] ?? 0
                ]
            );

            $id = $this->db->lastInsertId();
            $result = $this->db->query("SELECT * FROM priorities WHERE id = ?", [$id]);

            Response::created($result[0]);
        } catch (Exception $e) {
            Response::serverError('Failed to create priority');
        }
    }

    /**
     * POST /api/config/statuses
     */
    public function createStatus()
    {
        Auth::authenticate();
        Auth::requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true);

        $validator = new Validator();
        $validator->required('name', $data['name'] ?? null);

        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }

        try {
            $this->db->execute(
                "INSERT INTO statuses (name, color, kanban_column, order_index) VALUES (?, ?, ?, ?)",
                [
                    $data['name'],
                    $data['color'] ?? '#6B7280',
                    $data['kanban_column'] ?? 'todo',
                    $data['order_index'] ?? 0
                ]
            );

            $id = $this->db->lastInsertId();
            $result = $this->db->query("SELECT * FROM statuses WHERE id = ?", [$id]);

            Response::created($result[0]);
        } catch (Exception $e) {
            Response::serverError('Failed to create status');
        }
    }

    /**
     * POST /api/config/main-topics
     */
    public function createMainTopic()
    {
        Auth::authenticate();
        Auth::requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true);

        $validator = new Validator();
        $validator->required('name', $data['name'] ?? null);

        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }

        try {
            $this->db->execute(
                "INSERT INTO main_topics (name, description) VALUES (?, ?)",
                [$data['name'], $data['description'] ?? null]
            );

            $id = $this->db->lastInsertId();
            $result = $this->db->query("SELECT * FROM main_topics WHERE id = ?", [$id]);

            Response::created($result[0]);
        } catch (Exception $e) {
            Response::serverError('Failed to create main topic');
        }
    }

    /**
     * POST /api/config/sub-topics
     */
    public function createSubTopic()
    {
        Auth::authenticate();
        Auth::requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true);

        $validator = new Validator();
        $validator
            ->required('name', $data['name'] ?? null)
            ->required('main_topic_id', $data['main_topic_id'] ?? null)
            ->isInt('main_topic_id', $data['main_topic_id'] ?? null);

        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }

        try {
            $this->db->execute(
                "INSERT INTO sub_topics (main_topic_id, name, description) VALUES (?, ?, ?)",
                [$data['main_topic_id'], $data['name'], $data['description'] ?? null]
            );

            $id = $this->db->lastInsertId();
            $result = $this->db->query(
                "SELECT st.*, mt.name as main_topic_name
                 FROM sub_topics st
                 JOIN main_topics mt ON st.main_topic_id = mt.id
                 WHERE st.id = ?",
                [$id]
            );

            Response::created($result[0]);
        } catch (Exception $e) {
            Response::serverError('Failed to create sub topic');
        }
    }
}

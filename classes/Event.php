<?php
/**
 * JobNexus - Event Model Class (Calendar/Interviews)
 */

require_once __DIR__ . '/Database.php';

class Event
{
  private PDO $db;

  public function __construct()
  {
    $this->db = db();
  }

  /**
   * Create new event
   */
  public function create(array $data): ?int
  {
    $sql = "INSERT INTO events (
            application_id, hr_user_id, seeker_user_id, event_title, event_type,
            event_date, event_time, duration_minutes, timezone, location,
            meeting_link, description, status
        ) VALUES (
            :application_id, :hr_user_id, :seeker_user_id, :event_title, :event_type,
            :event_date, :event_time, :duration_minutes, :timezone, :location,
            :meeting_link, :description, :status
        )";

    $stmt = $this->db->prepare($sql);
    $stmt->execute([
      'application_id' => $data['application_id'] ?? null,
      'hr_user_id' => $data['hr_user_id'],
      'seeker_user_id' => $data['seeker_user_id'],
      'event_title' => $data['event_title'],
      'event_type' => $data['event_type'] ?? 'interview',
      'event_date' => $data['event_date'],
      'event_time' => $data['event_time'],
      'duration_minutes' => $data['duration_minutes'] ?? 60,
      'timezone' => $data['timezone'] ?? 'UTC',
      'location' => $data['location'] ?? null,
      'meeting_link' => $data['meeting_link'] ?? null,
      'description' => $data['description'] ?? null,
      'status' => 'scheduled'
    ]);

    return (int) $this->db->lastInsertId();
  }

  /**
   * Find event by ID
   */
  public function findById(int $id): ?array
  {
    $sql = "SELECT e.*, 
                       a.job_id, j.title as job_title,
                       sp.first_name, sp.last_name, sp.profile_photo,
                       c.company_name, c.logo as company_logo
                FROM events e
                LEFT JOIN applications a ON e.application_id = a.id
                LEFT JOIN jobs j ON a.job_id = j.id
                LEFT JOIN users u ON e.seeker_user_id = u.id
                LEFT JOIN seeker_profiles sp ON u.id = sp.user_id
                LEFT JOIN companies c ON e.hr_user_id = c.hr_user_id
                WHERE e.id = :id LIMIT 1";
    $stmt = $this->db->prepare($sql);
    $stmt->execute(['id' => $id]);
    return $stmt->fetch() ?: null;
  }

  /**
   * Get events for HR
   */
  public function getByHR(int $hrUserId, ?string $startDate = null, ?string $endDate = null): array
  {
    $where = "e.hr_user_id = :hr_user_id";
    $params = ['hr_user_id' => $hrUserId];

    if ($startDate) {
      $where .= " AND e.event_date >= :start_date";
      $params['start_date'] = $startDate;
    }

    if ($endDate) {
      $where .= " AND e.event_date <= :end_date";
      $params['end_date'] = $endDate;
    }

    $sql = "SELECT e.*, 
                       sp.first_name, sp.last_name, sp.profile_photo,
                       j.title as job_title
                FROM events e
                LEFT JOIN applications a ON e.application_id = a.id
                LEFT JOIN jobs j ON a.job_id = j.id
                LEFT JOIN users u ON e.seeker_user_id = u.id
                LEFT JOIN seeker_profiles sp ON u.id = sp.user_id
                WHERE $where
                ORDER BY e.event_date ASC, e.event_time ASC";

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
  }

  /**
   * Get events for Seeker
   */
  public function getBySeeker(int $seekerUserId, ?string $startDate = null, ?string $endDate = null): array
  {
    $where = "e.seeker_user_id = :seeker_user_id";
    $params = ['seeker_user_id' => $seekerUserId];

    if ($startDate) {
      $where .= " AND e.event_date >= :start_date";
      $params['start_date'] = $startDate;
    }

    if ($endDate) {
      $where .= " AND e.event_date <= :end_date";
      $params['end_date'] = $endDate;
    }

    $sql = "SELECT e.*, 
                       c.company_name, c.logo as company_logo,
                       j.title as job_title
                FROM events e
                LEFT JOIN applications a ON e.application_id = a.id
                LEFT JOIN jobs j ON a.job_id = j.id
                LEFT JOIN companies c ON e.hr_user_id = c.hr_user_id
                WHERE $where
                ORDER BY e.event_date ASC, e.event_time ASC";

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
  }

  /**
   * Get upcoming events
   */
  public function getUpcoming(int $userId, string $role, int $limit = 5): array
  {
    $userColumn = $role === 'hr' ? 'hr_user_id' : 'seeker_user_id';

    $sql = "SELECT e.*, 
                       j.title as job_title,
                       CASE WHEN :role = 'hr' THEN CONCAT(sp.first_name, ' ', sp.last_name) 
                            ELSE c.company_name END as participant_name,
                       CASE WHEN :role = 'hr' THEN sp.profile_photo 
                            ELSE c.logo END as participant_photo
                FROM events e
                LEFT JOIN applications a ON e.application_id = a.id
                LEFT JOIN jobs j ON a.job_id = j.id
                LEFT JOIN users u ON e.seeker_user_id = u.id
                LEFT JOIN seeker_profiles sp ON u.id = sp.user_id
                LEFT JOIN companies c ON e.hr_user_id = c.hr_user_id
                WHERE e.$userColumn = :user_id 
                  AND e.event_date >= CURDATE()
                  AND e.status IN ('scheduled', 'confirmed')
                ORDER BY e.event_date ASC, e.event_time ASC
                LIMIT :limit";

    $stmt = $this->db->prepare($sql);
    $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue('role', $role);
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
  }

  /**
   * Update event status
   */
  public function updateStatus(int $id, string $status): bool
  {
    $sql = "UPDATE events SET status = :status WHERE id = :id";
    $stmt = $this->db->prepare($sql);
    return $stmt->execute(['id' => $id, 'status' => $status]);
  }

  /**
   * Update event
   */
  public function update(int $id, array $data): bool
  {
    $fields = [];
    $params = ['id' => $id];

    foreach ($data as $key => $value) {
      if ($key !== 'id') {
        $fields[] = "$key = :$key";
        $params[$key] = $value;
      }
    }

    $sql = "UPDATE events SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $this->db->prepare($sql);
    return $stmt->execute($params);
  }

  /**
   * Delete event
   */
  public function delete(int $id): bool
  {
    $sql = "DELETE FROM events WHERE id = :id";
    $stmt = $this->db->prepare($sql);
    return $stmt->execute(['id' => $id]);
  }

  /**
   * Get events count
   */
  public function getCount(int $userId, string $role): array
  {
    $userColumn = $role === 'hr' ? 'hr_user_id' : 'seeker_user_id';

    $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN event_date = CURDATE() THEN 1 ELSE 0 END) as today,
                    SUM(CASE WHEN event_date > CURDATE() THEN 1 ELSE 0 END) as upcoming,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                FROM events
                WHERE $userColumn = :user_id";

    $stmt = $this->db->prepare($sql);
    $stmt->execute(['user_id' => $userId]);
    return $stmt->fetch();
  }
}

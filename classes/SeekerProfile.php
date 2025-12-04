<?php
/**
 * JobNexus - Seeker Profile Model Class
 */

require_once __DIR__ . '/Database.php';

class SeekerProfile
{
  private PDO $db;

  public function __construct()
  {
    $this->db = db();
  }

  /**
   * Create new seeker profile
   */
  public function create(array $data): ?int
  {
    $sql = "INSERT INTO seeker_profiles (
            user_id, first_name, last_name, headline, phone, location
        ) VALUES (
            :user_id, :first_name, :last_name, :headline, :phone, :location
        )";

    $stmt = $this->db->prepare($sql);
    $stmt->execute([
      'user_id' => $data['user_id'],
      'first_name' => $data['first_name'],
      'last_name' => $data['last_name'],
      'headline' => $data['headline'] ?? null,
      'phone' => $data['phone'] ?? null,
      'location' => $data['location'] ?? null
    ]);

    return (int) $this->db->lastInsertId();
  }

  /**
   * Find profile by user ID
   */
  public function findByUserId(int $userId): ?array
  {
    $sql = "SELECT sp.*, u.email
                FROM seeker_profiles sp
                LEFT JOIN users u ON sp.user_id = u.id
                WHERE sp.user_id = :user_id LIMIT 1";
    $stmt = $this->db->prepare($sql);
    $stmt->execute(['user_id' => $userId]);
    $profile = $stmt->fetch();

    if ($profile) {
      if ($profile['skills']) {
        $profile['skills'] = json_decode($profile['skills'], true);
      }

      // Get education
      $profile['education'] = $this->getEducation($profile['id']);

      // Get experience
      $profile['experience'] = $this->getExperience($profile['id']);
    }

    return $profile ?: null;
  }

  /**
   * Find profile by ID
   */
  public function findById(int $id): ?array
  {
    $sql = "SELECT sp.*, u.email
                FROM seeker_profiles sp
                LEFT JOIN users u ON sp.user_id = u.id
                WHERE sp.id = :id LIMIT 1";
    $stmt = $this->db->prepare($sql);
    $stmt->execute(['id' => $id]);
    $profile = $stmt->fetch();

    if ($profile) {
      if ($profile['skills']) {
        $profile['skills'] = json_decode($profile['skills'], true);
      }
      $profile['education'] = $this->getEducation($id);
      $profile['experience'] = $this->getExperience($id);
    }

    return $profile ?: null;
  }

  /**
   * Update profile
   */
  public function update(int $id, array $data): bool
  {
    $fields = [];
    $params = ['id' => $id];

    foreach ($data as $key => $value) {
      if ($key !== 'id') {
        $fields[] = "$key = :$key";
        if ($key === 'skills' && is_array($value)) {
          $params[$key] = json_encode($value);
        } else {
          $params[$key] = $value;
        }
      }
    }

    $sql = "UPDATE seeker_profiles SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $this->db->prepare($sql);
    $result = $stmt->execute($params);

    // Update profile completion
    if ($result) {
      $this->calculateCompletion($id);
    }

    return $result;
  }

  /**
   * Update by user ID
   */
  public function updateByUserId(int $userId, array $data): bool
  {
    $profile = $this->findByUserId($userId);
    if ($profile) {
      return $this->update($profile['id'], $data);
    }
    return false;
  }

  /**
   * Get education entries
   */
  public function getEducation(int $seekerId): array
  {
    $sql = "SELECT * FROM education WHERE seeker_id = :seeker_id ORDER BY end_date DESC, start_date DESC";
    $stmt = $this->db->prepare($sql);
    $stmt->execute(['seeker_id' => $seekerId]);
    return $stmt->fetchAll();
  }

  /**
   * Add education
   */
  public function addEducation(int $seekerId, array $data): ?int
  {
    $sql = "INSERT INTO education (
            seeker_id, degree, field_of_study, institution, location,
            start_date, end_date, is_current, grade, description
        ) VALUES (
            :seeker_id, :degree, :field_of_study, :institution, :location,
            :start_date, :end_date, :is_current, :grade, :description
        )";

    $stmt = $this->db->prepare($sql);
    $stmt->execute([
      'seeker_id' => $seekerId,
      'degree' => $data['degree'],
      'field_of_study' => $data['field_of_study'] ?? null,
      'institution' => $data['institution'],
      'location' => $data['location'] ?? null,
      'start_date' => $data['start_date'] ?? null,
      'end_date' => $data['end_date'] ?? null,
      'is_current' => $data['is_current'] ?? 0,
      'grade' => $data['grade'] ?? null,
      'description' => $data['description'] ?? null
    ]);

    $this->calculateCompletion($seekerId);
    return (int) $this->db->lastInsertId();
  }

  /**
   * Update education
   */
  public function updateEducation(int $id, array $data): bool
  {
    $fields = [];
    $params = ['id' => $id];

    foreach ($data as $key => $value) {
      if ($key !== 'id') {
        $fields[] = "$key = :$key";
        $params[$key] = $value;
      }
    }

    $sql = "UPDATE education SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $this->db->prepare($sql);
    return $stmt->execute($params);
  }

  /**
   * Delete education
   */
  public function deleteEducation(int $id): bool
  {
    $sql = "DELETE FROM education WHERE id = :id";
    $stmt = $this->db->prepare($sql);
    return $stmt->execute(['id' => $id]);
  }

  /**
   * Get experience entries
   */
  public function getExperience(int $seekerId): array
  {
    $sql = "SELECT * FROM experience WHERE seeker_id = :seeker_id ORDER BY end_date DESC, start_date DESC";
    $stmt = $this->db->prepare($sql);
    $stmt->execute(['seeker_id' => $seekerId]);
    $experiences = $stmt->fetchAll();

    foreach ($experiences as &$exp) {
      if ($exp['achievements']) {
        $exp['achievements'] = json_decode($exp['achievements'], true);
      }
    }

    return $experiences;
  }

  /**
   * Add experience
   */
  public function addExperience(int $seekerId, array $data): ?int
  {
    // Convert date formats to MySQL format (YYYY-MM-DD)
    $startDate = $this->formatDateForMySQL($data['start_date']);
    $endDate = !empty($data['end_date']) ? $this->formatDateForMySQL($data['end_date']) : null;

    $sql = "INSERT INTO experience (
            seeker_id, job_title, company_name, location, location_type,
            start_date, end_date, is_current, description, achievements
        ) VALUES (
            :seeker_id, :job_title, :company_name, :location, :location_type,
            :start_date, :end_date, :is_current, :description, :achievements
        )";

    $stmt = $this->db->prepare($sql);
    $stmt->execute([
      'seeker_id' => $seekerId,
      'job_title' => $data['job_title'],
      'company_name' => $data['company_name'],
      'location' => $data['location'] ?? null,
      'location_type' => $data['location_type'] ?? 'onsite',
      'start_date' => $startDate,
      'end_date' => $endDate,
      'is_current' => $data['is_current'] ?? 0,
      'description' => $data['description'] ?? null,
      'achievements' => isset($data['achievements']) ? json_encode($data['achievements']) : null
    ]);

    $this->calculateCompletion($seekerId);
    return (int) $this->db->lastInsertId();
  }

  /**
   * Convert various date formats to MySQL format (YYYY-MM-DD)
   */
  private function formatDateForMySQL($date): ?string
  {
    if (empty($date)) {
      return null;
    }

    // If already in YYYY-MM-DD format
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
      return $date;
    }

    // Try to parse various formats
    $formats = ['d-m-Y', 'd/m/Y', 'm-d-Y', 'm/d/Y', 'Y/m/d'];
    foreach ($formats as $format) {
      $parsed = \DateTime::createFromFormat($format, $date);
      if ($parsed !== false) {
        return $parsed->format('Y-m-d');
      }
    }

    // Try strtotime as fallback
    $timestamp = strtotime($date);
    if ($timestamp !== false) {
      return date('Y-m-d', $timestamp);
    }

    return null;
  }

  /**
   * Update experience
   */
  public function updateExperience(int $id, array $data): bool
  {
    $fields = [];
    $params = ['id' => $id];

    foreach ($data as $key => $value) {
      if ($key !== 'id') {
        $fields[] = "$key = :$key";
        if ($key === 'achievements' && is_array($value)) {
          $params[$key] = json_encode($value);
        } elseif (($key === 'start_date' || $key === 'end_date') && !empty($value)) {
          $params[$key] = $this->formatDateForMySQL($value);
        } else {
          $params[$key] = $value;
        }
      }
    }

    $sql = "UPDATE experience SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $this->db->prepare($sql);
    return $stmt->execute($params);
  }

  /**
   * Delete experience
   */
  public function deleteExperience(int $id): bool
  {
    $sql = "DELETE FROM experience WHERE id = :id";
    $stmt = $this->db->prepare($sql);
    return $stmt->execute(['id' => $id]);
  }

  /**
   * Calculate profile completion percentage
   */
  public function calculateCompletion(int $seekerId): int
  {
    $profile = $this->findById($seekerId);
    if (!$profile)
      return 0;

    $totalFields = 10;
    $filledFields = 0;

    if (!empty($profile['first_name']))
      $filledFields++;
    if (!empty($profile['last_name']))
      $filledFields++;
    if (!empty($profile['headline']))
      $filledFields++;
    if (!empty($profile['phone']))
      $filledFields++;
    if (!empty($profile['location']))
      $filledFields++;
    if (!empty($profile['bio']))
      $filledFields++;
    if (!empty($profile['profile_photo']))
      $filledFields++;
    if (!empty($profile['resume_file_path']))
      $filledFields++;
    if (!empty($profile['skills']))
      $filledFields++;
    if (!empty($profile['experience']))
      $filledFields++;

    $completion = round(($filledFields / $totalFields) * 100);

    $sql = "UPDATE seeker_profiles SET profile_completion = :completion WHERE id = :id";
    $stmt = $this->db->prepare($sql);
    $stmt->execute(['completion' => $completion, 'id' => $seekerId]);

    return $completion;
  }

  /**
   * Search seekers
   */
  public function search(array $filters = [], int $page = 1, int $perPage = 10): array
  {
    $offset = ($page - 1) * $perPage;
    $where = ["sp.is_available = 1"];
    $params = [];

    if (!empty($filters['search'])) {
      $where[] = "(sp.first_name LIKE :search OR sp.last_name LIKE :search OR sp.headline LIKE :search)";
      $params['search'] = '%' . $filters['search'] . '%';
    }

    if (!empty($filters['skills'])) {
      $where[] = "JSON_CONTAINS(sp.skills, :skills)";
      $params['skills'] = json_encode($filters['skills']);
    }

    $whereClause = implode(' AND ', $where);

    $countSql = "SELECT COUNT(*) FROM seeker_profiles sp WHERE $whereClause";
    $stmt = $this->db->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();

    $sql = "SELECT sp.*, u.email
                FROM seeker_profiles sp
                LEFT JOIN users u ON sp.user_id = u.id
                WHERE $whereClause
                ORDER BY sp.profile_completion DESC, sp.created_at DESC
                LIMIT :limit OFFSET :offset";

    $stmt = $this->db->prepare($sql);
    foreach ($params as $key => $value) {
      $stmt->bindValue($key, $value);
    }
    $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return [
      'profiles' => $stmt->fetchAll(),
      'total' => $total,
      'pages' => ceil($total / $perPage),
      'current_page' => $page
    ];
  }
}

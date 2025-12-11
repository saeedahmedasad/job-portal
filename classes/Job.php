<?php
/**
 * JobNexus - Job Model Class
 */

require_once __DIR__ . '/Database.php';

class Job
{
  private PDO $db;

  public function __construct()
  {
    $this->db = db();
  }

  /**
   * Create new job
   */
  public function create(array $data): ?int
  {
    $sql = "INSERT INTO jobs (
            company_id, posted_by, title, slug, description, requirements, 
            responsibilities, benefits, category_id, job_type, location_type,
            location, salary_min, salary_max, salary_currency, show_salary,
            experience_level, experience_years_min, experience_years_max,
            skills_required, application_deadline, positions_available, status
        ) VALUES (
            :company_id, :posted_by, :title, :slug, :description, :requirements,
            :responsibilities, :benefits, :category_id, :job_type, :location_type,
            :location, :salary_min, :salary_max, :salary_currency, :show_salary,
            :experience_level, :experience_years_min, :experience_years_max,
            :skills_required, :application_deadline, :positions_available, :status
        )";

    $slug = generateSlug($data['title']) . '-' . uniqid();

    $stmt = $this->db->prepare($sql);
    $stmt->execute([
      'company_id' => $data['company_id'],
      'posted_by' => $data['posted_by'],
      'title' => $data['title'],
      'slug' => $slug,
      'description' => $data['description'],
      'requirements' => $data['requirements'] ?? null,
      'responsibilities' => $data['responsibilities'] ?? null,
      'benefits' => $data['benefits'] ?? null,
      'category_id' => $data['category_id'] ?? null,
      'job_type' => $data['job_type'] ?? 'full-time',
      'location_type' => $data['location_type'] ?? 'onsite',
      'location' => $data['location'] ?? null,
      'salary_min' => $data['salary_min'] ?? null,
      'salary_max' => $data['salary_max'] ?? null,
      'salary_currency' => $data['salary_currency'] ?? 'USD',
      'show_salary' => $data['show_salary'] ?? 1,
      'experience_level' => $data['experience_level'] ?? 'mid',
      'experience_years_min' => $data['experience_years_min'] ?? null,
      'experience_years_max' => $data['experience_years_max'] ?? null,
      'skills_required' => isset($data['skills_required']) ? json_encode($data['skills_required']) : null,
      'application_deadline' => $data['application_deadline'] ?? null,
      'positions_available' => $data['positions_available'] ?? 1,
      'status' => $data['status'] ?? 'draft'
    ]);

    return (int) $this->db->lastInsertId();
  }

  /**
   * Find job by ID
   */
  public function findById(int $id): ?array
  {
    $sql = "SELECT j.*, c.company_name, c.logo, c.website, c.industry, c.verification_status,
                       cat.name as category_name, cat.slug as category_slug
                FROM jobs j
                LEFT JOIN companies c ON j.company_id = c.id
                LEFT JOIN job_categories cat ON j.category_id = cat.id
                WHERE j.id = :id LIMIT 1";
    $stmt = $this->db->prepare($sql);
    $stmt->execute(['id' => $id]);
    $job = $stmt->fetch();

    if ($job && $job['skills_required']) {
      $job['skills_required'] = json_decode($job['skills_required'], true);
    }

    return $job ?: null;
  }

  /**
   * Find job by slug
   */
  public function findBySlug(string $slug): ?array
  {
    $sql = "SELECT j.*, c.company_name, c.logo, c.website, c.industry,
                       cat.name as category_name
                FROM jobs j
                LEFT JOIN companies c ON j.company_id = c.id
                LEFT JOIN job_categories cat ON j.category_id = cat.id
                WHERE j.slug = :slug LIMIT 1";
    $stmt = $this->db->prepare($sql);
    $stmt->execute(['slug' => $slug]);
    $job = $stmt->fetch();

    if ($job && $job['skills_required']) {
      $job['skills_required'] = json_decode($job['skills_required'], true);
    }

    return $job ?: null;
  }

  /**
   * Get active jobs with filters and pagination
   */
  public function getActive(array $filters = [], int $page = 1, int $perPage = JOBS_PER_PAGE): array
  {
    $offset = ($page - 1) * $perPage;
    $where = ["j.status = 'active'", "c.verification_status = 'verified'"];
    $params = [];

    // Search filter - use separate placeholders for each occurrence
    if (!empty($filters['search'])) {
      $where[] = "(j.title LIKE :search1 OR j.description LIKE :search2 OR c.company_name LIKE :search3)";
      $searchTerm = '%' . $filters['search'] . '%';
      $params['search1'] = $searchTerm;
      $params['search2'] = $searchTerm;
      $params['search3'] = $searchTerm;
    }

    // Category filter
    if (!empty($filters['category'])) {
      $where[] = "j.category_id = :category";
      $params['category'] = $filters['category'];
    }

    // Location type filter
    if (!empty($filters['location_type'])) {
      $where[] = "j.location_type = :location_type";
      $params['location_type'] = $filters['location_type'];
    }

    // Job type filter
    if (!empty($filters['job_type'])) {
      $where[] = "j.job_type = :job_type";
      $params['job_type'] = $filters['job_type'];
    }

    // Experience level filter
    if (!empty($filters['experience_level'])) {
      $where[] = "j.experience_level = :experience_level";
      $params['experience_level'] = $filters['experience_level'];
    }

    // Salary range filter
    if (!empty($filters['salary_min'])) {
      $where[] = "j.salary_min >= :salary_min";
      $params['salary_min'] = $filters['salary_min'];
    }

    $whereClause = implode(' AND ', $where);

    // Sorting
    $orderBy = "j.is_featured DESC, j.published_at DESC";
    if (!empty($filters['sort'])) {
      switch ($filters['sort']) {
        case 'newest':
          $orderBy = "j.published_at DESC";
          break;
        case 'oldest':
          $orderBy = "j.published_at ASC";
          break;
        case 'salary_high':
          $orderBy = "j.salary_max DESC";
          break;
        case 'salary_low':
          $orderBy = "j.salary_min ASC";
          break;
      }
    }

    // Get total count
    $countSql = "SELECT COUNT(*) FROM jobs j
                     LEFT JOIN companies c ON j.company_id = c.id
                     WHERE $whereClause";
    $stmt = $this->db->prepare($countSql);

    // Bind count query parameters
    foreach ($params as $key => $value) {
      $stmt->bindValue(':' . $key, $value);
    }
    $stmt->execute();
    $total = $stmt->fetchColumn();

    // Get jobs
    $sql = "SELECT j.*, c.company_name, c.logo, c.industry,
                       cat.name as category_name, cat.icon as category_icon
                FROM jobs j
                LEFT JOIN companies c ON j.company_id = c.id
                LEFT JOIN job_categories cat ON j.category_id = cat.id
                WHERE $whereClause
                ORDER BY $orderBy
                LIMIT :limit OFFSET :offset";

    $stmt = $this->db->prepare($sql);
    foreach ($params as $key => $value) {
      $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $jobs = $stmt->fetchAll();

    // Decode skills for each job
    foreach ($jobs as &$job) {
      if ($job['skills_required']) {
        $job['skills_required'] = json_decode($job['skills_required'], true);
      }
    }

    return [
      'jobs' => $jobs,
      'total' => $total,
      'pages' => ceil($total / $perPage),
      'current_page' => $page
    ];
  }

  /**
   * Get jobs by company
   */
  public function getByCompany(int $companyId, ?string $status = null): array
  {
    $where = "j.company_id = :company_id";
    $params = ['company_id' => $companyId];

    if ($status) {
      $where .= " AND j.status = :status";
      $params['status'] = $status;
    }

    $sql = "SELECT j.*, cat.name as category_name,
                       (SELECT COUNT(*) FROM applications WHERE job_id = j.id) as applications_count
                FROM jobs j
                LEFT JOIN job_categories cat ON j.category_id = cat.id
                WHERE $where
                ORDER BY j.created_at DESC";

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
  }

  /**
   * Get recommended jobs for seeker
   */
  public function getRecommended(array $seekerProfile, int $limit = 6): array
  {
    $where = ["j.status = 'active'", "c.verification_status = 'verified'"];
    $params = [];

    // Match by category preference
    if (!empty($seekerProfile['target_job_category'])) {
      $sql = "SELECT id FROM job_categories WHERE name LIKE :cat OR slug LIKE :cat LIMIT 1";
      $stmt = $this->db->prepare($sql);
      $stmt->execute(['cat' => '%' . $seekerProfile['target_job_category'] . '%']);
      $cat = $stmt->fetch();
      if ($cat) {
        $where[] = "j.category_id = :category_id";
        $params['category_id'] = $cat['id'];
      }
    }

    // Match by location preference
    if (!empty($seekerProfile['preferred_location_type']) && $seekerProfile['preferred_location_type'] !== 'any') {
      $where[] = "j.location_type = :location_type";
      $params['location_type'] = $seekerProfile['preferred_location_type'];
    }

    $whereClause = implode(' AND ', $where);

    $sql = "SELECT j.*, c.company_name, c.logo, cat.name as category_name
                FROM jobs j
                LEFT JOIN companies c ON j.company_id = c.id
                LEFT JOIN job_categories cat ON j.category_id = cat.id
                WHERE $whereClause
                ORDER BY j.is_featured DESC, j.published_at DESC
                LIMIT :limit";

    $stmt = $this->db->prepare($sql);
    foreach ($params as $key => $value) {
      $stmt->bindValue($key, $value);
    }
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Update job
   */
  public function update(int $id, array $data): bool
  {
    $fields = [];
    $params = ['id' => $id];

    foreach ($data as $key => $value) {
      if ($key !== 'id') {
        $fields[] = "$key = :$key";
        if ($key === 'skills_required' && is_array($value)) {
          $params[$key] = json_encode($value);
        } else {
          $params[$key] = $value;
        }
      }
    }

    $sql = "UPDATE jobs SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $this->db->prepare($sql);
    return $stmt->execute($params);
  }

  /**
   * Publish job
   */
  public function publish(int $id): bool
  {
    $sql = "UPDATE jobs SET status = 'active', published_at = NOW() WHERE id = :id";
    $stmt = $this->db->prepare($sql);
    return $stmt->execute(['id' => $id]);
  }

  /**
   * Toggle job status
   */
  public function toggleStatus(int $id, string $status): bool
  {
    $sql = "UPDATE jobs SET status = :status WHERE id = :id";
    $stmt = $this->db->prepare($sql);
    return $stmt->execute(['id' => $id, 'status' => $status]);
  }

  /**
   * Increment view count
   */
  public function incrementViews(int $id): void
  {
    $sql = "UPDATE jobs SET views_count = views_count + 1 WHERE id = :id";
    $stmt = $this->db->prepare($sql);
    $stmt->execute(['id' => $id]);
  }

  /**
   * Get job stats
   */
  public function getStats(?int $companyId = null): array
  {
    $where = $companyId ? "WHERE company_id = :company_id" : "";
    $params = $companyId ? ['company_id' => $companyId] : [];

    $sql = "SELECT 
                    COUNT(*) as total_jobs,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_jobs,
                    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_jobs,
                    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_jobs,
                    SUM(views_count) as total_views,
                    SUM(applications_count) as total_applications
                FROM jobs $where";

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
  }

  /**
   * Get all categories with active job counts
   */
  public function getCategories(): array
  {
    $sql = "SELECT c.*, 
            (SELECT COUNT(*) FROM jobs j 
             LEFT JOIN companies comp ON j.company_id = comp.id 
             WHERE j.category_id = c.id 
             AND j.status = 'active'
             AND comp.verification_status = 'verified') as job_count 
            FROM job_categories c 
            WHERE c.is_active = 1 
            ORDER BY c.name ASC";
    $stmt = $this->db->query($sql);
    return $stmt->fetchAll();
  }

  /**
   * Delete job
   */
  public function delete(int $id): bool
  {
    $sql = "DELETE FROM jobs WHERE id = :id";
    $stmt = $this->db->prepare($sql);
    return $stmt->execute(['id' => $id]);
  }

  /**
   * Get featured jobs
   */
  public function getFeatured(int $limit = 6): array
  {
    $sql = "SELECT j.*, c.company_name, c.logo, cat.name as category_name
                FROM jobs j
                LEFT JOIN companies c ON j.company_id = c.id
                LEFT JOIN job_categories cat ON j.category_id = cat.id
                WHERE j.status = 'active' AND c.verification_status = 'verified'
                ORDER BY j.is_featured DESC, j.published_at DESC
                LIMIT :limit";

    $stmt = $this->db->prepare($sql);
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
  }
}

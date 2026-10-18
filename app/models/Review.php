
<?php

class Review
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getByPlaceId(
        int $placeId,
        bool $includeInactive = false
    ): array {
        $sql = "
            SELECT
                r.id,
                r.user_id,
                r.place_id,
                r.rating,
                r.comment,
                r.created_at,
                r.is_active,
                u.username
            FROM reviews r
            INNER JOIN users u ON r.user_id = u.id
            WHERE r.place_id = :place_id
        ";

        if (!$includeInactive) {
            $sql .= " AND r.is_active = 1";
        }

        $sql .= " ORDER BY r.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['place_id' => $placeId]);

        return $stmt->fetchAll();
    }
    
    
    public function getAll(
        string $search = '',
        ?int $placeId = null,
        string $status = 'all',
        ?int $rating = null
    ): array {
        $sql = "
            SELECT
                r.id,
                r.user_id,
                r.place_id,
                r.rating,
                r.comment,
                r.created_at,
                r.is_active,
                u.username,
                p.name AS place_name
            FROM reviews r
            INNER JOIN users u ON r.user_id = u.id
            INNER JOIN places p ON r.place_id = p.id
            WHERE 1 = 1
        ";

        $params = [];

        if (trim($search) !== '') {
            $sql .= "
                AND (
                    u.username LIKE :username_search
                    OR p.name LIKE :place_search
                    OR r.comment LIKE :comment_search
                )
            ";

            $searchTerm = '%' . trim($search) . '%';

            $params['username_search'] = $searchTerm;
            $params['place_search'] = $searchTerm;
            $params['comment_search'] = $searchTerm;
        }

        if ($placeId !== null && $placeId > 0) {
            $sql .= " AND r.place_id = :place_id";
            $params['place_id'] = $placeId;
        }

        if ($status === 'active') {
            $sql .= " AND r.is_active = 1";
        } elseif ($status === 'inactive') {
            $sql .= " AND r.is_active = 0";
        }

        if ($rating !== null && $rating >= 1 && $rating <= 5) {
            $sql .= " AND r.rating = :rating";
            $params['rating'] = $rating;
        }

        $sql .= " ORDER BY r.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function getPlacesWithReviews(): array
    {
        $sql = "
            SELECT DISTINCT p.id, p.name
            FROM places p
            INNER JOIN reviews r ON r.place_id = p.id
            ORDER BY p.name ASC
        ";

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll();
    }

    public function getSummary(int $placeId): array
    {
        $sql = "
            SELECT
                COUNT(*) AS total_reviews,
                AVG(rating) AS average_rating
            FROM reviews
            WHERE place_id = :place_id
              AND is_active = 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['place_id' => $placeId]);

        $summary = $stmt->fetch();

        return [
            'total_reviews' => (int) $summary['total_reviews'],
            'average_rating' => $summary['average_rating'] !== null
                ? (float) $summary['average_rating']
                : null
        ];
    }

    public function create(
        int $userId,
        int $placeId,
        int $rating,
        ?string $comment
    ): int {
        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException(
                'Rating must be between 1 and 5.'
            );
        }

        $placeStmt = $this->db->prepare("
            SELECT is_active
            FROM places
            WHERE id = :place_id
        ");

        $placeStmt->execute(['place_id' => $placeId]);

        if ((int) $placeStmt->fetchColumn() !== 1) {
            throw new InvalidArgumentException(
                'This place is unavailable for reviews.'
            );
        }

        $sql = "
            INSERT INTO reviews (
                user_id,
                place_id,
                rating,
                comment
            )
            VALUES (
                :user_id,
                :place_id,
                :rating,
                :comment
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'place_id' => $placeId,
            'rating' => $rating,
            'comment' => $comment
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function hasUserReviewed(
        int $userId,
        int $placeId
    ): bool {
    
        $sql = "
            SELECT 1
            FROM reviews
            WHERE user_id = :user_id
              AND place_id = :place_id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'place_id' => $placeId
        ]);

        return $stmt->fetchColumn() !== false;
    }

    public function delete(int $id): bool
    {
        $sql = "
            UPDATE reviews
            SET is_active = 0
            WHERE id = :id
              AND is_active = 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function restore(int $id): bool
    {
        $sql = "
            UPDATE reviews
            SET is_active = 1
            WHERE id = :id
              AND is_active = 0
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }
}

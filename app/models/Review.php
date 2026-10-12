
<?php

class Review
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getByPlaceId(int $placeId): array
    {
        $sql = "
            SELECT
                r.id,
                r.user_id,
                r.place_id,
                r.rating,
                r.comment,
                r.created_at,
                u.username
            FROM reviews r
            INNER JOIN users u ON r.user_id = u.id
            WHERE r.place_id = :place_id
            ORDER BY r.created_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['place_id' => $placeId]);

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

}

<?php

class PlaceSuggestion
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(
        int $userId,
        string $name,
        string $description,
        string $address,
        float $latitude,
        float $longitude
    ): int {
        $sql = "
            INSERT INTO place_suggestions
                (user_id, name, description, address, latitude, longitude)
            VALUES
                (:user_id, :name, :description, :address, :latitude, :longitude)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id'     => $userId,
            'name'        => $name,
            'description' => $description,
            'address'     => $address,
            'latitude'    => $latitude,
            'longitude'   => $longitude,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function getAll(string $status = 'pending'): array
    {
        $sql = "
            SELECT
                s.*,
                u.username AS submitted_by
            FROM place_suggestions s
            INNER JOIN users u ON u.id = s.user_id
            WHERE s.status = :status
            ORDER BY s.created_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['status' => $status]);
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM place_suggestions WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function approve(int $id, int $adminId): bool
    {
        $suggestion = $this->getById($id);
        if (!$suggestion || $suggestion['status'] !== 'pending') {
            return false;
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO places (name, description, address, latitude, longitude)
                VALUES (:name, :description, :address, :latitude, :longitude)
            ");
            $stmt->execute([
                'name'        => $suggestion['name'],
                'description' => $suggestion['description'],
                'address'     => $suggestion['address'],
                'latitude'    => $suggestion['latitude'],
                'longitude'   => $suggestion['longitude'],
            ]);

            $this->db->prepare("
                UPDATE place_suggestions
                SET status = 'approved',
                    reviewed_by = :admin,
                    reviewed_at = NOW()
                WHERE id = :id
            ")->execute(['admin' => $adminId, 'id' => $id]);

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function reject(int $id, int $adminId, string $note = ''): bool
    {
        $stmt = $this->db->prepare("
            UPDATE place_suggestions
            SET status = 'rejected',
                admin_note = :note,
                reviewed_by = :admin,
                reviewed_at = NOW()
            WHERE id = :id
              AND status = 'pending'
        ");

        return $stmt->execute([
            'id'    => $id,
            'admin' => $adminId,
            'note'  => $note === '' ? null : $note,
        ]) && $stmt->rowCount() > 0;
    }
}
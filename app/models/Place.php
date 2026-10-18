<?php

class Place
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getAll(
        string $search = '',
        string $tagSlug = '',
        bool $includeInactive = false
    ): array {
        $this->db->exec('SET SESSION group_concat_max_len = 8192');

        $sql = "
            SELECT
                p.id,
                p.name,
                p.description,
                p.address,
                p.is_active,
                p.latitude,
                p.longitude,
                p.image_url,
                GROUP_CONCAT(
                    DISTINCT t.name
                    ORDER BY t.name
                    SEPARATOR ', '
                ) AS tags
            FROM places p
            LEFT JOIN place_tags pt ON p.id = pt.place_id
            LEFT JOIN tags t ON pt.tag_id = t.id
            WHERE 1 = 1
        ";

        $params = [];

        if (!$includeInactive) {
            $sql .= " AND p.is_active = 1";
        }

        if ($search !== '') {
            $sql .= "
                AND (
                    p.name LIKE :name
                    OR p.address LIKE :address
                    OR p.description LIKE :description
                )
            ";

            $term = '%' . $search . '%';

            $params['name'] = $term;
            $params['address'] = $term;
            $params['description'] = $term;
        }

        if ($tagSlug !== '') {
            $sql .= "
                AND EXISTS (
                    SELECT 1
                    FROM place_tags filter_pt
                    INNER JOIN tags filter_t
                        ON filter_t.id = filter_pt.tag_id
                    WHERE filter_pt.place_id = p.id
                      AND filter_t.slug = :tag_slug
                )
            ";

            $params['tag_slug'] = $tagSlug;
        }

        $sql .= "
            GROUP BY
                p.id,
                p.name,
                p.description,
                p.address,
                p.is_active,
                p.latitude,
                p.longitude,
                p.image_url
            ORDER BY p.name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function getAllTags(): array
    {
        $sql = "
            SELECT id, name, slug
            FROM tags
            ORDER BY name ASC
        ";

        return $this->db->query($sql)->fetchAll();
    }

    public function getById(
        int $id,
        bool $includeInactive = false
    ): ?array {
        $sql = "
            SELECT
                p.id,
                p.name,
                p.description,
                p.address,
                p.latitude,
                p.longitude,
                p.is_active,
                p.image_url,
                GROUP_CONCAT(
                    DISTINCT t.name
                    ORDER BY t.name
                    SEPARATOR ', '
                ) AS tags
            FROM places p
            LEFT JOIN place_tags pt ON p.id = pt.place_id
            LEFT JOIN tags t ON pt.tag_id = t.id
            WHERE p.id = :id
        ";

        if (!$includeInactive) {
            $sql .= " AND p.is_active = 1";
        }

        $sql .= "
            GROUP BY
                p.id,
                p.name,
                p.description,
                p.address,
                p.latitude,
                p.longitude,
                p.is_active,
                p.image_url
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);

        $place = $stmt->fetch();

        return $place ?: null;
    }

    public function create(
        string $name,
        string $description,
        string $address,
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $imageUrl = null
    ): int {
        $sql = "
            INSERT INTO places (
                name,
                description,
                address,
                latitude,
                longitude,
                image_url
            )
            VALUES (
                :name,
                :description,
                :address,
                :latitude,
                :longitude,
                :image_url
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'name'        => $name,
            'description' => $description,
            'address'     => $address,
            'latitude'    => $latitude,
            'longitude'   => $longitude,
            'image_url'   => $imageUrl,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(
        int $id,
        string $name,
        string $description,
        string $address,
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $imageUrl = null
    ): bool {
        $sql = "
            UPDATE places
            SET name = :name,
                description = :description,
                address = :address,
                latitude = :latitude,
                longitude = :longitude,
                image_url = :image_url
            WHERE id = :id
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'id'          => $id,
            'name'        => $name,
            'description' => $description,
            'address'     => $address,
            'latitude'    => $latitude,
            'longitude'   => $longitude,
            'image_url'   => $imageUrl,
        ]);
    }

    public function delete(int $id): bool
    {
        $sql = "
            UPDATE places
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
            UPDATE places
            SET is_active = 1
            WHERE id = :id
              AND is_active = 0
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function setTags(int $placeId, array $tagIds): void
    {
        $tagIds = array_values(array_unique(array_map('intval', $tagIds)));
        $tagIds = array_filter($tagIds, fn($id) => $id > 0);

        $this->db->beginTransaction();
        try {
            $this->db->prepare('DELETE FROM place_tags WHERE place_id = :id')
                     ->execute(['id' => $placeId]);

            if (!empty($tagIds)) {
                $stmt = $this->db->prepare(
                    'INSERT INTO place_tags (place_id, tag_id) VALUES (:pid, :tid)'
                );
                foreach ($tagIds as $tid) {
                    $stmt->execute(['pid' => $placeId, 'tid' => $tid]);
                }
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getTagIdsForPlace(int $placeId): array
    {
        $stmt = $this->db->prepare(
            'SELECT tag_id FROM place_tags WHERE place_id = :id'
        );
        $stmt->execute(['id' => $placeId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}
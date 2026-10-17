

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
        string $tagSlug = ''
    ): array {
        $sql = "
            SELECT
                p.id,
                p.name,
                p.description,
                p.address,
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
                p.address
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

    
public function getById(int $id): ?array
{
    $sql = "
        SELECT
            p.id,
            p.name,
            p.description,
            p.address,
            p.latitude,
            p.longitude,
            GROUP_CONCAT(
                DISTINCT t.name
                ORDER BY t.name
                SEPARATOR ', '
            ) AS tags
        FROM places p
        LEFT JOIN place_tags pt ON p.id = pt.place_id
        LEFT JOIN tags t ON pt.tag_id = t.id
        WHERE p.id = :id
        GROUP BY
            p.id,
            p.name,
            p.description,
            p.address,
            p.latitude,
            p.longitude
    ";

    $stmt = $this->db->prepare($sql);
    $stmt->execute(['id' => $id]);

    $place = $stmt->fetch();

    return $place ?: null;
}


public function create(
    string $name,
    string $description,
    string $address
): int {
    $sql = "
        INSERT INTO places (name, description, address)
        VALUES (:name, :description, :address)
    ";

    $stmt = $this->db->prepare($sql);
    $stmt->execute([
        'name' => $name,
        'description' => $description,
        'address' => $address
    ]);

    return (int) $this->db->lastInsertId();
}

public function update(
    int $id,
    string $name,
    string $description,
    string $address
): bool {
    $sql = "
        UPDATE places
        SET name = :name,
            description = :description,
            address = :address
        WHERE id = :id
    ";

    $stmt = $this->db->prepare($sql);

    return $stmt->execute([
        'id' => $id,
        'name' => $name,
        'description' => $description,
        'address' => $address
    ]);
}

public function delete(int $id): bool
{
    $sql = "DELETE FROM places WHERE id = :id";

    $stmt = $this->db->prepare($sql);
    $stmt->execute(['id' => $id]);

    return $stmt->rowCount() > 0;
}

}


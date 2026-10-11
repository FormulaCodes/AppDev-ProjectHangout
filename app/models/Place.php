

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
}


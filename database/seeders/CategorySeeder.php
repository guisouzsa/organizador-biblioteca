<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /** Disciplinas de referência (dados de classificação, não de livros). */
    public const NAMES = [
        'Língua Portuguesa', 'Literatura', 'Redação', 'Matemática', 'História', 'Geografia',
        'Filosofia', 'Sociologia', 'Biologia', 'Física', 'Química', 'Inglês', 'Espanhol',
        'Educação Física', 'Educação Digital', 'Outros',
    ];

    public function run(): void
    {
        foreach (self::NAMES as $name) {
            Category::findOrCreateByName($name);
        }
    }
}

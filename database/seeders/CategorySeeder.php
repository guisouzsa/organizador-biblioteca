<?php

namespace Database\Seeders;

use App\Enums\CategoryType;
use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /** Disciplinas de referência (dados de classificação, não de livros). */
    public const DISCIPLINES = [
        'Língua Portuguesa', 'Literatura', 'Redação', 'Matemática', 'História', 'Geografia',
        'Filosofia', 'Sociologia', 'Biologia', 'Física', 'Química', 'Inglês', 'Espanhol',
        'Educação Física', 'Educação Digital', 'Outros',
    ];

    public const GENRES = [
        'Romance', 'Conto', 'Poesia', 'Fantasia', 'Ficção científica', 'Suspense', 'Terror',
        'Biografia', 'Autobiografia', 'Literatura infantil', 'Literatura juvenil',
    ];

    public const SUBJECTS = [
        'Literatura brasileira', 'Literatura estrangeira', 'Política', 'Psicologia', 'Ciências',
        'Divulgação científica', 'Religião', 'Tecnologia', 'Obras de referência',
    ];

    public function run(): void
    {
        foreach (self::DISCIPLINES as $name) {
            Category::findOrCreateByName($name, CategoryType::Discipline);
        }
        foreach (self::GENRES as $name) {
            Category::findOrCreateByName($name, CategoryType::Genre);
        }
        foreach (self::SUBJECTS as $name) {
            Category::findOrCreateByName($name, CategoryType::Subject);
        }
    }
}

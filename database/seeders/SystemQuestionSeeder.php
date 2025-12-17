<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SystemQuestion;

class SystemQuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $questions = [
            // Catégorie: Culture générale
            [
                'category' => 'Culture générale',
                'question' => 'Quelle est la capitale de la France ?',
                'correct_answer' => 'Paris',
                'wrong_answers' => ['Londres', 'Berlin', 'Madrid'],
                'difficulty' => 'easy'
            ],
            [
                'category' => 'Culture générale',
                'question' => 'Quel est le plus grand océan du monde ?',
                'correct_answer' => 'Océan Pacifique',
                'wrong_answers' => ['Océan Atlantique', 'Océan Indien', 'Océan Arctique'],
                'difficulty' => 'medium'
            ],
            [
                'category' => 'Culture générale',
                'question' => 'En quelle année a eu lieu la Révolution française ?',
                'correct_answer' => '1789',
                'wrong_answers' => ['1799', '1779', '1809'],
                'difficulty' => 'medium'
            ],
            [
                'category' => 'Culture générale',
                'question' => 'Quel est le symbole chimique de l\'or ?',
                'correct_answer' => 'Au',
                'wrong_answers' => ['Or', 'Go', 'Ag'],
                'difficulty' => 'hard'
            ],
            [
                'category' => 'Culture générale',
                'question' => 'Combien de continents y a-t-il sur Terre ?',
                'correct_answer' => '7',
                'wrong_answers' => ['5', '6', '8'],
                'difficulty' => 'easy'
            ],

            // Catégorie: Sport
            [
                'category' => 'Sport',
                'question' => 'Combien de joueurs composent une équipe de football sur le terrain ?',
                'correct_answer' => '11',
                'wrong_answers' => ['10', '12', '9'],
                'difficulty' => 'easy'
            ],
            [
                'category' => 'Sport',
                'question' => 'Quel pays a remporté la Coupe du Monde de football en 2018 ?',
                'correct_answer' => 'France',
                'wrong_answers' => ['Brésil', 'Allemagne', 'Espagne'],
                'difficulty' => 'medium'
            ],
            [
                'category' => 'Sport',
                'question' => 'Combien de sets sont nécessaires pour gagner un match de tennis ?',
                'correct_answer' => '2 ou 3 selon le format',
                'wrong_answers' => ['1', '4', '5'],
                'difficulty' => 'medium'
            ],
            [
                'category' => 'Sport',
                'question' => 'Quel est le record du monde du 100 mètres hommes ?',
                'correct_answer' => '9,58 secondes',
                'wrong_answers' => ['9,69 secondes', '9,79 secondes', '9,88 secondes'],
                'difficulty' => 'hard'
            ],
            [
                'category' => 'Sport',
                'question' => 'Combien de joueurs composent une équipe de basket-ball sur le terrain ?',
                'correct_answer' => '5',
                'wrong_answers' => ['6', '4', '7'],
                'difficulty' => 'easy'
            ],

            // Catégorie: Histoire
            [
                'category' => 'Histoire',
                'question' => 'Qui a peint la Joconde ?',
                'correct_answer' => 'Léonard de Vinci',
                'wrong_answers' => ['Michel-Ange', 'Picasso', 'Van Gogh'],
                'difficulty' => 'easy'
            ],
            [
                'category' => 'Histoire',
                'question' => 'En quelle année a eu lieu la Première Guerre mondiale ?',
                'correct_answer' => '1914-1918',
                'wrong_answers' => ['1912-1916', '1916-1920', '1910-1914'],
                'difficulty' => 'medium'
            ],
            [
                'category' => 'Histoire',
                'question' => 'Quel était le nom du premier homme à marcher sur la Lune ?',
                'correct_answer' => 'Neil Armstrong',
                'wrong_answers' => ['Buzz Aldrin', 'Yuri Gagarin', 'John Glenn'],
                'difficulty' => 'medium'
            ],
            [
                'category' => 'Histoire',
                'question' => 'Qui a écrit "Les Misérables" ?',
                'correct_answer' => 'Victor Hugo',
                'wrong_answers' => ['Émile Zola', 'Gustave Flaubert', 'Alexandre Dumas'],
                'difficulty' => 'hard'
            ],
            [
                'category' => 'Histoire',
                'question' => 'Quelle était la capitale de l\'Empire romain ?',
                'correct_answer' => 'Rome',
                'wrong_answers' => ['Byzance', 'Athènes', 'Alexandrie'],
                'difficulty' => 'easy'
            ],

            // Catégorie: Science
            [
                'category' => 'Science',
                'question' => 'Quelle est la vitesse de la lumière ?',
                'correct_answer' => '299 792 458 m/s',
                'wrong_answers' => ['300 000 000 m/s', '250 000 000 m/s', '350 000 000 m/s'],
                'difficulty' => 'hard'
            ],
            [
                'category' => 'Science',
                'question' => 'Combien de planètes composent notre système solaire ?',
                'correct_answer' => '8',
                'wrong_answers' => ['7', '9', '10'],
                'difficulty' => 'easy'
            ],
            [
                'category' => 'Science',
                'question' => 'Quel est le symbole chimique de l\'eau ?',
                'correct_answer' => 'H2O',
                'wrong_answers' => ['H2O2', 'CO2', 'NaCl'],
                'difficulty' => 'medium'
            ],
            [
                'category' => 'Science',
                'question' => 'Quelle est la formule de l\'aire d\'un cercle ?',
                'correct_answer' => 'π × r²',
                'wrong_answers' => ['2 × π × r', 'π × r', 'π × d'],
                'difficulty' => 'medium'
            ],
            [
                'category' => 'Science',
                'question' => 'Quel est le gaz le plus abondant dans l\'atmosphère terrestre ?',
                'correct_answer' => 'Azote',
                'wrong_answers' => ['Oxygène', 'Dioxyde de carbone', 'Hydrogène'],
                'difficulty' => 'hard'
            ],

            // Catégorie: Géographie
            [
                'category' => 'Géographie',
                'question' => 'Quel est le plus grand pays du monde en superficie ?',
                'correct_answer' => 'Russie',
                'wrong_answers' => ['Canada', 'Chine', 'États-Unis'],
                'difficulty' => 'medium'
            ],
            [
                'category' => 'Géographie',
                'question' => 'Quelle est la plus haute montagne du monde ?',
                'correct_answer' => 'Mont Everest',
                'wrong_answers' => ['K2', 'Kilimandjaro', 'Mont Blanc'],
                'difficulty' => 'easy'
            ],
            [
                'category' => 'Géographie',
                'question' => 'Quel est le plus long fleuve du monde ?',
                'correct_answer' => 'Nil',
                'wrong_answers' => ['Amazone', 'Mississippi', 'Yangtsé'],
                'difficulty' => 'medium'
            ],
            [
                'category' => 'Géographie',
                'question' => 'Dans quel océan se trouve l\'île de Madagascar ?',
                'correct_answer' => 'Océan Indien',
                'wrong_answers' => ['Océan Atlantique', 'Océan Pacifique', 'Mer Méditerranée'],
                'difficulty' => 'hard'
            ],
            [
                'category' => 'Géographie',
                'question' => 'Quelle est la capitale du Japon ?',
                'correct_answer' => 'Tokyo',
                'wrong_answers' => ['Osaka', 'Kyoto', 'Yokohama'],
                'difficulty' => 'easy'
            ],

            // Catégorie: Cinéma
            [
                'category' => 'Cinéma',
                'question' => 'Qui a réalisé le film "Titanic" ?',
                'correct_answer' => 'James Cameron',
                'wrong_answers' => ['Steven Spielberg', 'Christopher Nolan', 'Martin Scorsese'],
                'difficulty' => 'medium'
            ],
            [
                'category' => 'Cinéma',
                'question' => 'Quel acteur a joué le rôle de Jack dans "Titanic" ?',
                'correct_answer' => 'Leonardo DiCaprio',
                'wrong_answers' => ['Brad Pitt', 'Tom Cruise', 'Johnny Depp'],
                'difficulty' => 'easy'
            ],
            [
                'category' => 'Cinéma',
                'question' => 'En quelle année est sorti le film "Avatar" ?',
                'correct_answer' => '2009',
                'wrong_answers' => ['2007', '2011', '2013'],
                'difficulty' => 'hard'
            ],
            [
                'category' => 'Cinéma',
                'question' => 'Quel est le nom du personnage principal de "Matrix" ?',
                'correct_answer' => 'Neo',
                'wrong_answers' => ['Morpheus', 'Trinity', 'Agent Smith'],
                'difficulty' => 'medium'
            ],
            [
                'category' => 'Cinéma',
                'question' => 'Qui a réalisé "Inception" ?',
                'correct_answer' => 'Christopher Nolan',
                'wrong_answers' => ['Quentin Tarantino', 'Ridley Scott', 'David Fincher'],
                'difficulty' => 'hard'
            ],

            // Catégorie: Musique
            [
                'category' => 'Musique',
                'question' => 'Quel groupe a chanté "Bohemian Rhapsody" ?',
                'correct_answer' => 'Queen',
                'wrong_answers' => ['The Beatles', 'Led Zeppelin', 'Pink Floyd'],
                'difficulty' => 'easy'
            ],
            [
                'category' => 'Musique',
                'question' => 'Combien de cordes a une guitare standard ?',
                'correct_answer' => '6',
                'wrong_answers' => ['4', '5', '7'],
                'difficulty' => 'easy'
            ],
            [
                'category' => 'Musique',
                'question' => 'Quel instrument de musique est associé à Mozart ?',
                'correct_answer' => 'Piano',
                'wrong_answers' => ['Violon', 'Flûte', 'Trompette'],
                'difficulty' => 'medium'
            ],
            [
                'category' => 'Musique',
                'question' => 'Quel est le nom du chanteur principal de "The Beatles" ?',
                'correct_answer' => 'John Lennon',
                'wrong_answers' => ['Paul McCartney', 'Ringo Starr', 'George Harrison'],
                'difficulty' => 'hard'
            ],
            [
                'category' => 'Musique',
                'question' => 'Combien de notes composent une octave ?',
                'correct_answer' => '8',
                'wrong_answers' => ['7', '12', '6'],
                'difficulty' => 'hard'
            ],

            // Catégorie: Littérature
            [
                'category' => 'Littérature',
                'question' => 'Qui a écrit "1984" ?',
                'correct_answer' => 'George Orwell',
                'wrong_answers' => ['Aldous Huxley', 'Ray Bradbury', 'H.G. Wells'],
                'difficulty' => 'medium'
            ],
            [
                'category' => 'Littérature',
                'question' => 'Quel est le premier livre de la série "Harry Potter" ?',
                'correct_answer' => 'Harry Potter à l\'école des sorciers',
                'wrong_answers' => ['Harry Potter et la Chambre des secrets', 'Harry Potter et le Prisonnier d\'Azkaban', 'Harry Potter et la Coupe de feu'],
                'difficulty' => 'easy'
            ],
            [
                'category' => 'Littérature',
                'question' => 'Qui a écrit "Le Petit Prince" ?',
                'correct_answer' => 'Antoine de Saint-Exupéry',
                'wrong_answers' => ['Jules Verne', 'Marcel Proust', 'Albert Camus'],
                'difficulty' => 'medium'
            ],
            [
                'category' => 'Littérature',
                'question' => 'Combien de romans composent "À la recherche du temps perdu" ?',
                'correct_answer' => '7',
                'wrong_answers' => ['5', '6', '8'],
                'difficulty' => 'hard'
            ],
            [
                'category' => 'Littérature',
                'question' => 'Qui a écrit "L\'Étranger" ?',
                'correct_answer' => 'Albert Camus',
                'wrong_answers' => ['Jean-Paul Sartre', 'André Malraux', 'Simone de Beauvoir'],
                'difficulty' => 'hard'
            ],
        ];

        foreach ($questions as $question) {
            SystemQuestion::create($question);
        }
    }
}


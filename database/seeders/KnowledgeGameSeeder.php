<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KnowledgeGameSeeder extends Seeder
{
    public function run(): void
    {
        $questions = [
            'farming' => [
                ['What is a useful first step before choosing a crop for a field?', ['Check the soil, rainfall and local market', 'Plant the same crop everywhere', 'Buy fertilizer before inspecting the field', 'Wait until harvest to plan'], 'a', 'Match crops to local growing conditions and likely buyers before investing.'],
                ['Why do farmers rotate crops, including legumes, where suitable?', ['To use more water', 'To support soil fertility and interrupt some pest cycles', 'To avoid keeping records', 'To make every harvest the same size'], 'b', 'A suitable rotation can support soil health and reduce the build-up of some crop-specific pests and diseases.'],
                ['What is a practical way to reduce soil erosion on sloping land?', ['Leave soil bare', 'Cultivate straight downhill', 'Use ground cover and follow contour practices', 'Burn crop residues on the slope'], 'c', 'Ground cover and contour-based practices help slow runoff and keep soil in place.'],
                ['When should a farmer inspect crops for pests and disease?', ['Regularly, so problems are noticed early', 'Only after the whole field is damaged', 'Only on market day', 'Never if it rained'], 'a', 'Regular scouting helps farmers identify problems early and choose an appropriate response.'],
                ['What should guide fertilizer use?', ['A neighbour’s guess only', 'The crop colour alone', 'A soil test or locally recommended guidance and the product label', 'Use as much as possible'], 'c', 'Use soil information and trusted local recommendations, and follow the product label to avoid waste or harm.'],
                ['Why dry grain properly before storage?', ['To make it heavier', 'To reduce mould and storage losses', 'To remove the need for a clean store', 'To make pests grow faster'], 'b', 'Dry grain and clean, dry storage help reduce mould and post-harvest losses.'],
                ['Which records help a farmer understand whether a crop enterprise is profitable?', ['Only the planting date', 'Inputs, labour, harvest, sales and expenses', 'Only the weather', 'Names of visitors'], 'b', 'Production and financial records help compare sales with the costs of producing the crop.'],
                ['What is a sensible first response when an unfamiliar pest appears?', ['Spray any available chemical immediately', 'Identify and assess the problem, then seek reliable local advice', 'Ignore it until harvest', 'Mix several pesticides together'], 'b', 'Correct identification and local advice help avoid ineffective or unsafe treatments.'],
                ['Why use mulch or other suitable soil cover?', ['To increase runoff', 'To protect soil and help conserve moisture', 'To stop all crop growth', 'To replace every farm record'], 'b', 'Suitable soil cover can reduce erosion and evaporation and support soil condition.'],
                ['How can a farmer reduce losses after harvest?', ['Harvest at the right maturity and handle and store produce carefully', 'Leave produce in the rain', 'Mix damaged produce with good produce', 'Delay sorting until it is sold'], 'a', 'Timely harvest, careful handling, sorting and suitable storage all help protect quality.'],
            ],
            'poultry' => [
                ['What should poultry have access to throughout the day?', ['Clean drinking water', 'Only water at feeding time', 'Sugary drinks', 'No water during hot weather'], 'a', 'Birds need reliable access to clean water. Check drinkers often, especially in warm weather.'],
                ['How should a brooder’s warmth be managed for young chicks?', ['Keep one temperature without observing chicks', 'Adjust warmth and observe chick behaviour', 'Remove all warmth at night', 'Place chicks in a draught'], 'b', 'Chick behaviour can indicate whether the brooder is too warm, too cool or draughty. Follow local husbandry guidance.'],
                ['What is a good biosecurity practice for a poultry flock?', ['Allow visitors to move freely between houses', 'Clean footwear and equipment and limit unnecessary visitors', 'Share dirty drinkers between farms', 'Mix new birds with the flock immediately'], 'b', 'Clean equipment, visitor controls and other biosecurity steps help reduce the spread of disease.'],
                ['What is the safest approach to poultry vaccination?', ['Use any vaccine at any time', 'Follow a schedule recommended by a qualified local animal-health professional', 'Skip records', 'Give extra doses when birds look tired'], 'b', 'Vaccination plans depend on local disease risks and product directions; consult a qualified animal-health professional.'],
                ['What should a farmer do when birds appear sick?', ['Separate affected birds where practical and seek qualified animal-health advice', 'Sell them without checking', 'Give human medicine at random', 'Move them to another flock'], 'a', 'Prompt isolation where practical and professional advice can help limit disease spread and guide care.'],
                ['Why should poultry feed suit the birds’ age and production stage?', ['Nutrient needs change as birds grow and produce', 'All birds need exactly the same feed', 'It makes clean water unnecessary', 'It prevents the need for housing'], 'a', 'Feed should meet the birds’ needs at their stage of growth or production; follow reputable local guidance.'],
                ['What is a sign that poultry litter needs attention?', ['It is dry and clean', 'It is persistently wet or strongly ammonia-smelling', 'Birds have clean water', 'Eggs are collected'], 'b', 'Wet litter and strong ammonia odour can signal ventilation, drinker or litter problems that need investigation.'],
                ['Which routine records are useful in a poultry enterprise?', ['Feed use, mortality, egg or meat output, sales and expenses', 'Only the colour of the house', 'Only visitor names', 'No records are useful'], 'a', 'Production and financial records help track flock performance and business costs.'],
                ['How should new birds be introduced to an existing flock?', ['Mix them immediately with no checks', 'Keep them separate for an appropriate observation period and seek local guidance', 'Share equipment without cleaning', 'Bring them in during a disease outbreak'], 'b', 'Separating new birds for observation and following local biosecurity advice can reduce disease introduction.'],
                ['Why collect eggs regularly and handle them carefully?', ['To reduce breakage and keep eggs clean', 'To make shells thinner', 'To avoid checking the flock', 'To keep eggs in direct sunlight'], 'a', 'Regular collection and careful, clean handling help protect egg quality and reduce losses.'],
            ],
        ];

        foreach ($questions as $topic => $items) {
            foreach ($items as [$question, $options, $correct, $explanation]) {
                DB::table('knowledge_game_questions')->updateOrInsert(
                    ['topic' => $topic, 'question' => $question],
                    [
                        'options' => json_encode(array_combine(['a', 'b', 'c', 'd'], $options), JSON_THROW_ON_ERROR),
                        'correct_option' => $correct,
                        'explanation' => $explanation,
                        'active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }
    }
}

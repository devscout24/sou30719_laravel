<?php

namespace Database\Seeders;

use App\Models\DatingPreference;
use App\Models\DatingProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds 100 dating-ready users (50 male, 50 female) with fully filled-out
 * DatingProfile + DatingPreference rows, so the Matches workspace has a real
 * candidate pool to search/rank against in dev/testing. No DatingImage rows
 * are created — image_path is expected to be a relative path on the public
 * storage disk (DatingImage::getFullUrlAttribute() does
 * asset('storage/'.$path)), not an external URL, and there's nothing real to
 * store it from here. Matches without a photo just render with photo=null,
 * which the Matches API already handles.
 */
class DatingProfileSeeder extends Seeder
{
    protected const OCCUPATIONS = [
        'Software Engineer', 'Teacher', 'Nurse', 'Graphic Designer', 'Chef',
        'Marketing Manager', 'Accountant', 'Photographer', 'Architect', 'Physical Therapist',
        'Data Analyst', 'Sales Manager', 'Veterinarian', 'Journalist', 'Electrician',
        'Interior Designer', 'Pharmacist', 'Musician', 'Firefighter', 'Product Manager',
    ];

    protected const EDUCATION = [
        "Bachelor's Degree", "Master's Degree", 'Associate Degree', 'PhD', 'Trade School',
        'Some College', "Bachelor's in Business", "Bachelor's in Engineering", "Master's in Fine Arts",
    ];

    protected const ETHNICITIES = [
        'Asian', 'Black', 'Hispanic/Latino', 'White', 'Middle Eastern', 'South Asian', 'Mixed', 'Native American', 'Pacific Islander',
    ];

    protected const BODY_TYPES = ['Slim', 'Athletic', 'Average', 'Curvy', 'Muscular', 'Full-figured'];

    protected const POLITICAL_VIEWS = ['Liberal', 'Moderate', 'Conservative', 'Apolitical', 'Progressive', 'Libertarian'];

    protected const CITIES = [
        'Austin', 'Seattle', 'Denver', 'Chicago', 'Boston', 'Portland', 'San Diego',
        'Nashville', 'Atlanta', 'Minneapolis', 'Miami', 'Phoenix', 'Dallas', 'Raleigh',
    ];

    protected const LANGUAGE_POOL = ['English', 'Spanish', 'French', 'Mandarin', 'German', 'Portuguese', 'Arabic', 'Hindi', 'Japanese'];

    protected const HOBBY_POOL = [
        'hiking', 'reading', 'cooking', 'gaming', 'traveling', 'photography', 'yoga',
        'painting', 'live music', 'dancing', 'cycling', 'swimming', 'rock climbing',
        'gardening', 'board games', 'running', 'camping', 'wine tasting', 'volunteering',
    ];

    protected const PERSONALITY_POOL = [
        'outgoing', 'adventurous', 'family-oriented', 'ambitious', 'laid-back', 'creative',
        'analytical', 'empathetic', 'humorous', 'introverted', 'optimistic', 'independent',
    ];

    protected const RELIGIONS = ['muslim', 'christian', 'hindu', 'buddhist', 'other', 'prefer_not_to_say'];
    protected const YES_NO_SOMETIMES = ['yes', 'no', 'occasionally'];
    protected const RELATIONSHIP_GOALS = ['casual', 'long_term', 'marriage', 'friendship', 'not_sure'];
    protected const LIFESTYLE_HABITS = ['active', 'moderate', 'sedentary'];
    protected const PET_PREFERENCES = ['loves_pets', 'no_pets', 'allergic', 'has_pets'];
    protected const FAMILY_PLANS = ['want_kids', 'open_to_kids', 'dont_want_kids', 'not_sure'];
    protected const CHILDREN_STATUS = ['no_kids', 'has_kids'];

    public function run(): void
    {
        foreach (['male', 'female'] as $gender) {
            for ($i = 1; $i <= 50; $i++) {
                $this->seedOne($gender, $i);
            }
        }
    }

    protected function seedOne(string $gender, int $index): void
    {
        $name  = fake()->name($gender === 'male' ? 'male' : 'female');
        $email = "seed.{$gender}.{$index}@matches.test";

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name'              => $name,
                'username'          => Str::slug($name) . '-' . $index,
                'password'          => Hash::make('12345678'),
                'status'            => 'active',
                'email_verified_at' => now(),
            ]
        );

        $height = $gender === 'male'
            ? $this->randomHeight(66, 76) // 5'6" – 6'4"
            : $this->randomHeight(60, 70); // 5'0" – 5'10"

        $dob = fake()->dateTimeBetween('-45 years', '-21 years')->format('Y-m-d');

        DatingProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'about_me'            => fake()->paragraph(3),
                'occupation'          => fake()->randomElement(self::OCCUPATIONS),
                'education'           => fake()->randomElement(self::EDUCATION),
                'relationship_goal'   => fake()->randomElement(self::RELATIONSHIP_GOALS),
                'looking_for'         => fake()->randomElement(['male', 'female', 'both']),
                'height'              => $height,
                'religion'            => fake()->randomElement(self::RELIGIONS),
                'smoking'             => fake()->randomElement(self::YES_NO_SOMETIMES),
                'drinking'            => fake()->randomElement(self::YES_NO_SOMETIMES),
                'is_active'           => true,

                'nickname'            => fake()->firstName(),
                'showcase_page'       => true,
                'city'                => fake()->randomElement(self::CITIES),
                'about'               => fake()->paragraph(4),
                'profile_setup_media' => null,

                'dating_nickname'     => fake()->firstName(),
                'dating_dob'          => $dob,
                'dating_full_name'    => $name,
                'relationship_status' => 'single',
                'dating_gender'       => $gender,
                'dating_email'        => $email,
                'dating_location'     => fake()->randomElement(self::CITIES),
                'dating_country'      => 'United States',
                'address_1'           => fake()->streetAddress(),
                'address_2'           => null,
                'connections_view'    => true,

                'lifestyle_habits'    => fake()->randomElement(self::LIFESTYLE_HABITS),
                'body_type'           => fake()->randomElement(self::BODY_TYPES),
                'ethnicity'           => fake()->randomElement(self::ETHNICITIES),
                'religious_beliefs'   => fake()->randomElement(self::RELIGIONS),
                'languages'           => fake()->randomElements(self::LANGUAGE_POOL, random_int(1, 3)),

                'hobbies'             => fake()->randomElements(self::HOBBY_POOL, random_int(3, 5)),
                'personality_traits'  => fake()->randomElements(self::PERSONALITY_POOL, random_int(2, 4)),
                'pet_preference'      => fake()->randomElement(self::PET_PREFERENCES),
                'political_views'     => fake()->randomElement(self::POLITICAL_VIEWS),
                'family_plans'        => fake()->randomElement(self::FAMILY_PLANS),
                'children_status'     => fake()->randomElement(self::CHILDREN_STATUS),
                'prompt_question'     => 'What does a perfect weekend look like?',
                'prompt_answer'       => fake()->sentence(12),

                'visual_description'  => fake()->sentence(15),
            ]
        );

        DatingPreference::updateOrCreate(
            ['user_id' => $user->id],
            [
                'interested_in'       => fake()->randomElement(['male', 'female', 'both']),
                'min_age'             => random_int(20, 30),
                'max_age'             => random_int(35, 55),
                'max_distance'        => random_int(10, 100),
                'relationship_goal'   => fake()->randomElement(self::RELATIONSHIP_GOALS),
                'deal_breakers'       => fake()->randomElement(['smoking', 'dishonesty', 'no ambition', 'poor hygiene', 'closed-mindedness']),
                'partner_preferences' => fake()->sentence(10),
            ]
        );
    }

    protected function randomHeight(int $minInches, int $maxInches): string
    {
        $inches = random_int($minInches, $maxInches);
        $feet   = intdiv($inches, 12);
        $remain = $inches % 12;

        return "{$feet}'{$remain}\"";
    }
}

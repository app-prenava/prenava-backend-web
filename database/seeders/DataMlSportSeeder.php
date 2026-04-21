<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DataMlSportSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $activities = [
            [
                'activity'   => 'walking',
                'video_link' => 'https://www.youtube.com/watch?v=njeZ29umqVE',
                'long_text'  => 'Jalan kaki adalah olahraga paling aman dan mudah untuk ibu hamil di semua trimester. Lakukan selama 20-30 menit per hari dengan kecepatan sedang. Jalan kaki membantu melancarkan peredaran darah, mengurangi risiko preeklampsia, dan mempersiapkan stamina untuk persalinan.',
            ],
            [
                'activity'   => 'prenatal_yoga',
                'video_link' => 'https://www.youtube.com/watch?v=B4Nh0MJMpig',
                'long_text'  => 'Yoga prenatal dirancang khusus untuk ibu hamil, fokus pada pernapasan, peregangan lembut, dan keseimbangan. Membantu mengurangi stres, meredakan nyeri punggung, serta meningkatkan fleksibilitas panggul untuk persiapan melahirkan.',
            ],
            [
                'activity'   => 'swimming',
                'video_link' => 'https://www.youtube.com/watch?v=sM7RGCVmjOo',
                'long_text'  => 'Berenang memberikan latihan seluruh tubuh tanpa membebani sendi. Air menopang berat badan sehingga mengurangi tekanan pada punggung dan kaki. Sangat baik untuk mengurangi bengkak kaki dan meningkatkan sirkulasi darah.',
            ],
            [
                'activity'   => 'stationary_cycling',
                'video_link' => 'https://www.youtube.com/watch?v=4E7r7rC7GFo',
                'long_text'  => 'Bersepeda statis aman karena tidak ada risiko jatuh. Membantu menjaga kebugaran kardiovaskular tanpa tekanan berlebih pada sendi. Atur intensitas rendah hingga sedang, dan pastikan posisi duduk nyaman.',
            ],
            [
                'activity'   => 'pelvic_floor',
                'video_link' => 'https://www.youtube.com/watch?v=aMxKE4B4v7c',
                'long_text'  => 'Latihan dasar panggul (Kegel) memperkuat otot-otot yang menopang rahim, kandung kemih, dan usus. Sangat penting untuk mencegah inkontinensia urin dan memperlancar proses persalinan. Lakukan 10-15 repetisi, 3 kali sehari.',
            ],
            [
                'activity'   => 'low_impact_aerobic',
                'video_link' => 'https://www.youtube.com/watch?v=Kul7Fjh-vdg',
                'long_text'  => 'Aerobik ringan menjaga kesehatan jantung dan paru-paru tanpa gerakan melompat. Fokus pada gerakan langkah samping, angkat lutut rendah, dan ayunan lengan. Lakukan 15-20 menit dengan intensitas yang nyaman.',
            ],
            [
                'activity'   => 'pilates_prenatal',
                'video_link' => 'https://www.youtube.com/watch?v=OFfV7x3N7RE',
                'long_text'  => 'Pilates prenatal memperkuat otot inti (core), punggung, dan dasar panggul. Gerakan dikontrol dan perlahan, cocok untuk menjaga postur tubuh yang berubah selama kehamilan. Hindari posisi telentang setelah trimester pertama.',
            ],
            [
                'activity'   => 'strength_light_resistance',
                'video_link' => 'https://www.youtube.com/watch?v=GIIGHzQMklM',
                'long_text'  => 'Latihan kekuatan ringan menggunakan resistance band atau beban ringan (1-3 kg) membantu menjaga massa otot dan kekuatan tubuh. Fokus pada lengan, kaki, dan punggung atas. Hindari mengangkat beban berat.',
            ],
            [
                'activity'   => 'stretching_gentle',
                'video_link' => 'https://www.youtube.com/watch?v=_dZkjdJiEeU',
                'long_text'  => 'Peregangan lembut membantu meredakan kekakuan otot dan meningkatkan fleksibilitas. Fokus pada area punggung, pinggul, bahu, dan betis. Tahan setiap posisi 15-30 detik tanpa memaksakan gerakan.',
            ],
            [
                'activity'   => 'breathing_exercise',
                'video_link' => 'https://www.youtube.com/watch?v=tybOi4hjZFQ',
                'long_text'  => 'Latihan pernapasan dalam membantu mengurangi kecemasan, menurunkan tekanan darah, dan mempersiapkan teknik pernapasan untuk persalinan. Tarik napas dalam 4 hitungan, tahan 4 hitungan, dan hembuskan 6 hitungan.',
            ],
            [
                'activity'   => 'tai_chi',
                'video_link' => 'https://www.youtube.com/watch?v=cEOS2zoyQw4',
                'long_text'  => 'Tai Chi adalah seni gerak perlahan yang meningkatkan keseimbangan, koordinasi, dan ketenangan mental. Gerakan mengalir dan lembut, sangat cocok untuk ibu hamil yang ingin olahraga dengan risiko cedera minimal.',
            ],
            [
                'activity'   => 'aqua_cycling',
                'video_link' => 'https://www.youtube.com/watch?v=MG4I_HeB7G0',
                'long_text'  => 'Bersepeda di dalam air menggabungkan manfaat bersepeda dan berenang. Air memberikan resistensi alami sekaligus menopang berat tubuh. Sangat efektif untuk melatih kaki tanpa tekanan pada sendi.',
            ],
            [
                'activity'   => 'balance_training',
                'video_link' => 'https://www.youtube.com/watch?v=7x7arSbcJbU',
                'long_text'  => 'Latihan keseimbangan membantu mengatasi perubahan pusat gravitasi selama kehamilan. Berdiri satu kaki, berjalan tumit-jari kaki, atau menggunakan balance board. Selalu dekat pegangan untuk keamanan.',
            ],
            [
                'activity'   => 'resistance_band_workout',
                'video_link' => 'https://www.youtube.com/watch?v=I0uhDZ06euE',
                'long_text'  => 'Latihan dengan resistance band memberikan tahanan yang lembut dan terkontrol. Cocok untuk memperkuat lengan, kaki, dan punggung tanpa risiko beban jatuh. Pilih band dengan tahanan ringan hingga sedang.',
            ],
            [
                'activity'   => 'seated_exercises',
                'video_link' => 'https://www.youtube.com/watch?v=nxLGkS0W-iM',
                'long_text'  => 'Latihan duduk sangat cocok untuk trimester ketiga atau saat kelelahan. Lakukan angkat lengan, rotasi bahu, angkat kaki, dan peregangan duduk. Tetap aktif meski dalam posisi duduk.',
            ],
            [
                'activity'   => 'water_walking',
                'video_link' => 'https://www.youtube.com/watch?v=aQxpoBjxWrM',
                'long_text'  => 'Jalan kaki di dalam air memberikan latihan kardio dengan tahanan alami air. Mengurangi beban pada sendi dan sangat menyegarkan. Ideal untuk ibu hamil yang mengalami bengkak kaki.',
            ],
            [
                'activity'   => 'light_dance',
                'video_link' => 'https://www.youtube.com/watch?v=z3TAz2_uwwU',
                'long_text'  => 'Menari ringan dengan musik favorit membantu menjaga kebugaran sekaligus meningkatkan mood. Hindari gerakan berputar cepat, melompat, atau gerakan tiba-tiba. Fokus pada gerakan mengalir dan menyenangkan.',
            ],
            [
                'activity'   => 'treadmill_walk',
                'video_link' => 'https://www.youtube.com/watch?v=njeZ29umqVE',
                'long_text'  => 'Jalan kaki di treadmill memungkinkan kontrol kecepatan dan kemiringan yang lebih presisi. Atur kecepatan 3-5 km/jam dengan kemiringan rendah. Selalu pegang pegangan samping untuk keamanan.',
            ],
            [
                'activity'   => 'prenatal_pilates_ball',
                'video_link' => 'https://www.youtube.com/watch?v=DRl-hbUVYr0',
                'long_text'  => 'Latihan dengan bola pilates (gym ball) membantu meredakan nyeri punggung, melatih keseimbangan, dan membuka panggul. Duduk dan mengayun di bola juga bisa membantu posisi bayi menjelang persalinan.',
            ],
            [
                'activity'   => 'modified_plank',
                'video_link' => 'https://www.youtube.com/watch?v=Efj9CgG3M_s',
                'long_text'  => 'Plank modifikasi dengan bertumpu pada lutut memperkuat otot inti tanpa tekanan berlebih pada perut. Tahan posisi 10-20 detik dan ulangi 3-5 kali. Hindari jika mengalami diastasis rekti.',
            ],
            [
                'activity'   => 'chair_yoga',
                'video_link' => 'https://www.youtube.com/watch?v=CrPNjHU4QJQ',
                'long_text'  => 'Yoga kursi menawarkan manfaat yoga dengan dukungan kursi untuk keseimbangan dan kenyamanan. Cocok untuk trimester akhir atau ibu hamil yang sulit berdiri lama. Fokus pada pernapasan dan peregangan tubuh atas.',
            ],
            [
                'activity'   => 'foam_rolling',
                'video_link' => 'https://www.youtube.com/watch?v=16jZ6OOz4k0',
                'long_text'  => 'Foam rolling membantu meredakan ketegangan otot dan meningkatkan sirkulasi darah. Gunakan pada betis, paha, dan punggung atas. Hindari area perut dan punggung bawah langsung. Lakukan dengan tekanan ringan.',
            ],
            [
                'activity'   => 'bodyweight_squat',
                'video_link' => 'https://www.youtube.com/watch?v=xqvCmoLULNY',
                'long_text'  => 'Squat tanpa beban memperkuat paha, pantat, dan dasar panggul. Buka kaki selebar bahu, turunkan badan perlahan, dan kembali berdiri. Gunakan kursi di belakang sebagai pengaman. Sangat baik untuk persiapan persalinan.',
            ],
            [
                'activity'   => 'arm_circles',
                'video_link' => 'https://www.youtube.com/watch?v=9JXbc1jM8EQ',
                'long_text'  => 'Lingkaran lengan meningkatkan mobilitas bahu dan mengurangi kekakuan. Putar lengan ke depan dan belakang dengan diameter kecil hingga besar. Mudah dilakukan kapan saja dan tidak memerlukan alat.',
            ],
            [
                'activity'   => 'elliptical_light',
                'video_link' => 'https://www.youtube.com/watch?v=0LhfjRkjNDk',
                'long_text'  => 'Elliptical dengan intensitas ringan memberikan latihan kardio tanpa dampak pada sendi. Gerakan elips yang halus mengurangi risiko cedera. Atur resistensi rendah dan lakukan 15-20 menit.',
            ],
            [
                'activity'   => 'side_leg_raise',
                'video_link' => 'https://www.youtube.com/watch?v=jghbMRIcp00',
                'long_text'  => 'Angkat kaki samping memperkuat otot pinggul dan paha luar, membantu stabilitas panggul yang penting selama kehamilan. Berbaring miring, angkat kaki atas perlahan, tahan 2 detik, lalu turunkan. Ulangi 10-15 kali per sisi.',
            ],
            [
                'activity'   => 'cat_cow_pose',
                'video_link' => 'https://www.youtube.com/watch?v=kqnua4rHVVA',
                'long_text'  => 'Pose kucing-sapi adalah gerakan yoga yang sangat efektif untuk meredakan nyeri punggung. Posisi merangkak, lengkungkan punggung ke atas (kucing) lalu ke bawah (sapi) secara bergantian. Sinkronkan dengan pernapasan.',
            ],
            [
                'activity'   => 'wall_push_up',
                'video_link' => 'https://www.youtube.com/watch?v=a6YHbXD2XlU',
                'long_text'  => 'Push-up dinding memperkuat dada, bahu, dan lengan tanpa tekanan pada perut. Berdiri menghadap dinding, letakkan tangan selebar bahu, tekuk siku untuk mendekati dinding lalu dorong kembali. Lakukan 10-15 repetisi.',
            ],
            [
                'activity'   => 'gentle_stairs_climb',
                'video_link' => 'https://www.youtube.com/watch?v=njeZ29umqVE',
                'long_text'  => 'Naik tangga perlahan melatih kekuatan kaki dan meningkatkan detak jantung secara alami. Gunakan pegangan tangga, naik satu anak tangga per langkah, dan jangan terburu-buru. Hindari jika mengalami sesak napas berlebihan.',
            ],
            [
                'activity'   => 'yoga_nidra',
                'video_link' => 'https://www.youtube.com/watch?v=M0u9GST_j3s',
                'long_text'  => 'Yoga Nidra atau "tidur yoga" adalah teknik relaksasi mendalam yang dilakukan dalam posisi berbaring. Sangat efektif mengurangi kecemasan, insomnia, dan stres menjelang persalinan. Durasi 20-40 menit per sesi.',
            ],
            [
                'activity'   => 'padel',
                'video_link' => 'https://www.youtube.com/watch?v=AK9MJmEhJiQ',
                'long_text'  => 'Padel adalah olahraga raket yang dimainkan di lapangan kecil. Hanya direkomendasikan untuk ibu hamil trimester kedua dengan risiko rendah dan sudah berpengalaman bermain sebelumnya. Konsultasikan dengan dokter terlebih dahulu.',
            ],
        ];

        foreach ($activities as $a) {
            DB::table('data_ml_sport')->updateOrInsert(
                ['activity' => $a['activity']],
                array_merge($a, [
                    'picture_1'  => null,
                    'picture_2'  => null,
                    'picture_3'  => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }
    }
}

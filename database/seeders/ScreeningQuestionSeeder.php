<?php

namespace Database\Seeders;

use App\Models\ScreeningQuestion;
use Illuminate\Database\Seeder;

class ScreeningQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $questions = [
            // ── Dimensi 1: Literasi Kesehatan Maternal (literacy) ─────────
            [
                'code'            => 'LIT_01',
                'dimension'       => 'literacy',
                'dimension_label' => 'Pemahaman Kehamilan',
                'question_text'   => 'Seberapa paham Bunda mengenai tanda-tanda bahaya kehamilan yang harus segera diperiksakan (seperti perdarahan, bengkak parah, nyeri kepala hebat, atau gerak janin berkurang)?',
                'options_json'    => [
                    ['value' => '1', 'label' => 'Belum tahu sama sekali', 'score' => 1],
                    ['value' => '2', 'label' => 'Pernah dengar sedikit, tapi belum yakin', 'score' => 2],
                    ['value' => '3', 'label' => 'Cukup paham sebagian besar tanda bahaya', 'score' => 3],
                    ['value' => '4', 'label' => 'Sangat paham dan tahu kapan harus segera ke tenaga kesehatan', 'score' => 4],
                ],
                'weight'   => 1,
                'order_no' => 1,
            ],
            [
                'code'            => 'LIT_02',
                'dimension'       => 'literacy',
                'dimension_label' => 'Pemahaman Kehamilan',
                'question_text'   => 'Ketika mendengar pantangan atau mitos kehamilan dari lingkungan sekitar/media sosial, bagaimana biasanya Bunda menyikapinya?',
                'options_json'    => [
                    ['value' => '1', 'label' => 'Langsung mengikuti karena takut terjadi apa-apa', 'score' => 1],
                    ['value' => '2', 'label' => 'Sering bingung membedakan mana mitos dan mana fakta medis', 'score' => 2],
                    ['value' => '3', 'label' => 'Biasanya mencari informasi tambahan dulu sebelum mengikuti', 'score' => 3],
                    ['value' => '4', 'label' => 'Selalu memverifikasi ke tenaga kesehatan atau sumber medis terpercaya', 'score' => 4],
                ],
                'weight'   => 1,
                'order_no' => 2,
            ],
            [
                'code'            => 'LIT_03',
                'dimension'       => 'literacy',
                'dimension_label' => 'Pemahaman Kehamilan',
                'question_text'   => 'Seberapa paham Bunda tentang kebutuhan gizi harian dan perkembangan janin sesuai usia kehamilan saat ini?',
                'options_json'    => [
                    ['value' => '1', 'label' => 'Belum memahami kebutuhan gizi dan tahap perkembangan janin', 'score' => 1],
                    ['value' => '2', 'label' => 'Baru tahu sedikit secara umum', 'score' => 2],
                    ['value' => '3', 'label' => 'Cukup paham kebutuhan gizi utama per trimester', 'score' => 3],
                    ['value' => '4', 'label' => 'Sangat paham dan sudah menerapkannya sehari-hari', 'score' => 4],
                ],
                'weight'   => 1,
                'order_no' => 3,
            ],

            // ── Dimensi 2: Risiko Gizi, Anemia & Stunting (nutrition) ─────
            [
                'code'            => 'NUT_01',
                'dimension'       => 'nutrition',
                'dimension_label' => 'Gizi & Kesehatan Fisik',
                'question_text'   => 'Dalam seminggu terakhir, bagaimana pola makan bergizi seimbang (protein, sayur, buah) dan kecukupan minum air putih (minimal 8 gelas/hari) Bunda?',
                'options_json'    => [
                    ['value' => '1', 'label' => 'Sangat jarang terpenuhi / makan dan minum sering tidak teratur', 'score' => 1],
                    ['value' => '2', 'label' => 'Kadang-kadang saja terpenuhi', 'score' => 2],
                    ['value' => '3', 'label' => 'Cukup sering terpenuhi di sebagian besar hari', 'score' => 3],
                    ['value' => '4', 'label' => 'Rutin terpenuhi hampir setiap hari', 'score' => 4],
                ],
                'weight'   => 1,
                'order_no' => 4,
            ],
            [
                'code'            => 'NUT_02',
                'dimension'       => 'nutrition',
                'dimension_label' => 'Gizi & Kesehatan Fisik',
                'question_text'   => 'Apakah Bunda sering merasakan keluhan seperti mudah pusing, cepat lelah, lemas, pucat, atau belum rutin mengonsumsi Tablet Tambah Darah (TTD)?',
                'options_json'    => [
                    ['value' => '1', 'label' => 'Sangat sering mengalami keluhan tersebut dan tidak rutin minum TTD', 'score' => 1],
                    ['value' => '2', 'label' => 'Cukup sering mengalami keluhan atau sering lupa minum TTD', 'score' => 2],
                    ['value' => '3', 'label' => 'Jarang mengalami keluhan dan cukup rutin minum TTD', 'score' => 3],
                    ['value' => '4', 'label' => 'Tidak ada keluhan dan rutin mengonsumsi TTD sesuai anjuran', 'score' => 4],
                ],
                'weight'   => 1,
                'order_no' => 5,
            ],
            [
                'code'            => 'NUT_03',
                'dimension'       => 'nutrition',
                'dimension_label' => 'Gizi & Kesehatan Fisik',
                'question_text'   => 'Seberapa rutin Bunda memantau asupan nutrisi pencegah stunting dan melakukan aktivitas fisik ringan yang aman untuk kehamilan?',
                'options_json'    => [
                    ['value' => '1', 'label' => 'Belum pernah memantau nutrisi khusus maupun berolahraga ringan', 'score' => 1],
                    ['value' => '2', 'label' => 'Baru sesekali saja melakukannya', 'score' => 2],
                    ['value' => '3', 'label' => 'Cukup rutin, meski belum terjadwal dengan rapi', 'score' => 3],
                    ['value' => '4', 'label' => 'Sudah rutin dan terencana setiap minggu', 'score' => 4],
                ],
                'weight'   => 1,
                'order_no' => 6,
            ],

            // ── Dimensi 3: Kesejahteraan Mental & Emosional (mental) ──────
            [
                'code'            => 'MEN_01',
                'dimension'       => 'mental',
                'dimension_label' => 'Ketenangan & Emosi',
                'question_text'   => 'Dalam 2 minggu terakhir, seberapa sering Bunda merasa sangat cemas, sedih berkepanjangan, atau mudah kewalahan menghadapi kehamilan?',
                'options_json'    => [
                    ['value' => '1', 'label' => 'Hampir setiap hari', 'score' => 1],
                    ['value' => '2', 'label' => 'Beberapa hari dalam seminggu', 'score' => 2],
                    ['value' => '3', 'label' => 'Jarang, hanya sesekali saja', 'score' => 3],
                    ['value' => '4', 'label' => 'Hampir tidak pernah, perasaan cenderung stabil dan tenang', 'score' => 4],
                ],
                'weight'   => 1,
                'order_no' => 7,
            ],
            [
                'code'            => 'MEN_02',
                'dimension'       => 'mental',
                'dimension_label' => 'Ketenangan & Emosi',
                'question_text'   => 'Bagaimana kualitas tidur dan tingkat stres Bunda saat memikirkan kondisi kehamilan atau persiapan persalinan?',
                'options_json'    => [
                    ['value' => '1', 'label' => 'Tidur sangat terganggu dan sering merasa tertekan/stres berat', 'score' => 1],
                    ['value' => '2', 'label' => 'Cukup sering susah tidur atau gelisah', 'score' => 2],
                    ['value' => '3', 'label' => 'Tidur cukup baik, sesekali saja merasa khawatir wajar', 'score' => 3],
                    ['value' => '4', 'label' => 'Tidur nyenyak dan merasa siap menghadapi kehamilan/persalinan', 'score' => 4],
                ],
                'weight'   => 1,
                'order_no' => 8,
            ],
            [
                'code'            => 'MEN_03',
                'dimension'       => 'mental',
                'dimension_label' => 'Ketenangan & Emosi',
                'question_text'   => 'Seberapa cukup dukungan emosional atau tempat bercerita yang Bunda rasakan dari pasangan, keluarga, atau sesama ibu hamil?',
                'options_json'    => [
                    ['value' => '1', 'label' => 'Merasa sendirian dan sangat jarang punya tempat bercerita', 'score' => 1],
                    ['value' => '2', 'label' => 'Dukungan ada tapi masih terasa kurang', 'score' => 2],
                    ['value' => '3', 'label' => 'Cukup mendapat dukungan dari orang terdekat', 'score' => 3],
                    ['value' => '4', 'label' => 'Sangat didukung dan nyaman berbagi cerita kapan pun dibutuhkan', 'score' => 4],
                ],
                'weight'   => 1,
                'order_no' => 9,
            ],

            // ── Dimensi 4: Kepatuhan Pemeriksaan & Akses Bidan (anc) ──────
            [
                'code'            => 'ANC_01',
                'dimension'       => 'anc',
                'dimension_label' => 'Kontrol & Layanan Bidan',
                'question_text'   => 'Seberapa teratur Bunda melakukan pemeriksaan kehamilan (ANC) ke bidan, puskesmas, atau dokter kandungan?',
                'options_json'    => [
                    ['value' => '1', 'label' => 'Belum pernah periksa atau sangat jarang periksa', 'score' => 1],
                    ['value' => '2', 'label' => 'Hanya periksa jika sedang ada keluhan saja', 'score' => 2],
                    ['value' => '3', 'label' => 'Cukup teratur, meski kadang terlewat dari jadwal', 'score' => 3],
                    ['value' => '4', 'label' => 'Selalu rutin periksa sesuai jadwal anjuran tenaga kesehatan', 'score' => 4],
                ],
                'weight'   => 1,
                'order_no' => 10,
            ],
            [
                'code'            => 'ANC_02',
                'dimension'       => 'anc',
                'dimension_label' => 'Kontrol & Layanan Bidan',
                'question_text'   => 'Seberapa mudah Bunda menemukan lokasi bidan/faskes terdekat dan mengatur jadwal konsultasi saat dibutuhkan?',
                'options_json'    => [
                    ['value' => '1', 'label' => 'Sangat kesulitan mencari bidan atau mengatur jadwal', 'score' => 1],
                    ['value' => '2', 'label' => 'Agak kesulitan dan sering bingung harus menghubungi siapa', 'score' => 2],
                    ['value' => '3', 'label' => 'Cukup mudah menemukan bidan/faskes di sekitar', 'score' => 3],
                    ['value' => '4', 'label' => 'Sangat mudah dan sudah memiliki bidan/faskes langganan tetap', 'score' => 4],
                ],
                'weight'   => 1,
                'order_no' => 11,
            ],
            [
                'code'            => 'ANC_03',
                'dimension'       => 'anc',
                'dimension_label' => 'Kontrol & Layanan Bidan',
                'question_text'   => 'Apakah Bunda terbiasa mencatat hasil pemeriksaan kehamilan dan memantau usia kehamilan serta Hari Perkiraan Lahir (HPL)?',
                'options_json'    => [
                    ['value' => '1', 'label' => 'Belum pernah mencatat dan belum tahu pasti usia kehamilan/HPL', 'score' => 1],
                    ['value' => '2', 'label' => 'Jarang mencatat, hanya mengingat secara kasar', 'score' => 2],
                    ['value' => '3', 'label' => 'Cukup sering mencatat di buku KIA atau ponsel', 'score' => 3],
                    ['value' => '4', 'label' => 'Selalu mencatat rutin dan memantau perkembangan HPL secara berkala', 'score' => 4],
                ],
                'weight'   => 1,
                'order_no' => 12,
            ],
        ];

        foreach ($questions as $q) {
            ScreeningQuestion::updateOrCreate(
                ['code' => $q['code']],
                array_merge($q, ['is_active' => true, 'version' => 'v1'])
            );
        }
    }
}

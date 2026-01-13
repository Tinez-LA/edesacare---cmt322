<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>USM eDesaCare | Hostel Complaint Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="assets/homepage_style.css">
</head>
<body>
<div class="overlay">
  <!-- NAVBAR -->
  <nav class="navbar navbar-expand-lg navbar-dark px-4">
    <a class="navbar-brand fw-bold" href="#">USM eDesaCare</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
      <ul class="navbar-nav">
        <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
        <li class="nav-item"><a class="nav-link" href="#hostels">Desasiswa</a></li>
        <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
        <li class="nav-item"><a class="btn btn-light text-dark mx-2" href="auth/login.php"><i class="bi bi-box-arrow-in-right me-2"></i>Login</a></li>
      </ul>
    </div>
  </nav>

  <!-- HERO -->
  <section class="hero text-center">
    <h1>Welcome to <span style="color: var(--orange);">USM eDesaCare</span></h1>
    <p>Your voice matters. Report, resolve, and enhance hostel life across Universiti Sains Malaysia.</p>
    <a href="auth/login.php" class="btn-gradient mt-3">📝 Submit a Complaint</a>
  </section>

  <!-- HOSTELS -->
  <section id="hostels" class="container my-5">
    <h2 class="section-title text-center">Our Desasiswa (Hostels)</h2>

    <!-- Kampus Induk -->
    <div class="campus-block">
      <h4 class="text-center text-secondary mb-4">🏫 Kampus Induk</h4>
      <div class="row g-4 justify-content-center">
        <?php
          $induk = [
            ["Aman Damai", "https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSjM3O0lwMPKZKB764KufsoShPt9aFd02Ob0A&s", "Modern and serene hostel designed for student collaboration and rest.", "https://directory.usm.my/?direktoristaf/direktori&kod=00181"],
            ["Bakti Permai", "https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTyD1rZzkkuDd_IGEorLYiYuNVz2m7kPo-ZkQ&s", "A vibrant living space fostering unity among residents.", "https://directory.usm.my/?direktoristaf/direktori&kod=01434"],
            ["Cahaya Gemilang", "https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSYyfRCkLd-CD2djX0NZQKu40q7PPS23aNEDg&s", "Bright and inspiring environment for academic success.", "https://directory.usm.my/?direktoristaf/direktori&kod=01433"],
            ["Indah Kembara", "https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRwXOQnhJmvqk_D66S3q0l9Jiig0wmnu93Lfw&s", "Comfort meets convenience with green surroundings.", "https://directory.usm.my/?direktoristaf/direktori&kod=00182"],
            ["Restu", "https://restu.usm.my/templates/yootheme/cache/HAC%20WEB%20Restu%20M01%20Twin-b3ac31cc-8305054e.jpeg", "Restful environment promoting peace and harmony.", "https://restu.usm.my/"],
            ["Saujana", "https://tekun.usm.my/images/SejarahTekun/sejarahtekun.jpg", "Blends nature and community spirit for balanced campus life.", "https://directory.usm.my/?direktoristaf/direktori&kod=00765"],
            ["Tekun", "https://news.usm.my/images/phocafavicon/5EB3460D-CE0A-4F37-B09D-4C7149040327.jpeg", "Dynamic living quarters close to campus facilities.", "https://tekun.usm.my/"],
            ["Fajar Harapan", "https://www.usm.my/images/USM/hostel/0d061542-1156-4565-83a7-26eea3497938.jpg", "Symbol of new beginnings and student excellence.", "https://directory.usm.my/?direktoristaf/direktori&kod=01433"],
            ["Rumah Antarabangsa", "https://som.usm.my/images/Accommodation_4.jpg", "Cultural diversity hub for international students.", "https://som.usm.my/index.php/students/accommodation"]
          ];
          foreach ($induk as $h) {
            echo "
            <div class='col-md-4 col-lg-3'>
              <div class='card h-100'>
                <img src='{$h[1]}' alt='{$h[0]}'>
                <div class='card-body text-center'>
                  <h5 class='card-title'>Desasiswa {$h[0]}</h5>
                  <p class='card-text'>{$h[2]}</p>
                  <a href='{$h[3]}' target='_blank' class='btn btn-sm'>View Details</a>
                </div>
              </div>
            </div>";
          }
      ?>
      </div>
    </div>

    <!-- Kampus Kejuruteraan -->
    <div class="campus-block">
      <br>
      <h4 class="text-center text-secondary mb-4">⚙️ Kampus Kejuruteraan</h4>
      <div class="row g-4 justify-content-center">
        <?php
          $eng = [
            ["Jaya", "https://hac.eng.usm.my/templates/yootheme/cache/75/05-75627773.jpeg", "Modern, well-equipped dormitory for engineering students.", "https://hac.eng.usm.my/index.php?view=article&id=9&catid=2"],
            ["Lembaran", "https://hac.eng.usm.my/templates/yootheme/cache/ae/02-ae2f2f38.jpeg", "Home of innovation and creativity within the engineering campus.", "https://hac.eng.usm.my/index.php?view=article&id=10&catid=2"],
            ["Utama", "https://hac.eng.usm.my/templates/yootheme/cache/97/02-972a91ee.jpeg", "Central hostel offering easy access to labs and lecture halls.", "https://hac.eng.usm.my/index.php?view=article&id=11&catid=2"]
          ];
          foreach ($eng as $h) {
            echo "
            <div class='col-md-4 col-lg-3'>
              <div class='card h-100'>
                <img src='{$h[1]}' alt='{$h[0]}'>
                <div class='card-body text-center'>
                  <h5 class='card-title'>Desasiswa {$h[0]}</h5>
                  <p class='card-text'>{$h[2]}</p>
                  <a href='{$h[3]}' target='_blank' class='btn btn-sm'>View Details</a>
                </div>
              </div>
            </div>";
          }
        ?>
      </div>
    </div>

    <!-- Kampus Kesihatan -->
    <div class="campus-block">
      <br>
      <h4 class="text-center text-secondary mb-4">💉 Kampus Kesihatan</h4>
      <div class="row g-4 justify-content-center">
        <?php
        $health = [
          ["Murni", "https://hws2023.kk.usm.my/templates/yootheme/cache/7a/desamurni2-7aee4de9.jpeg", "A calm hostel for medical and health sciences students.", "https://directory.usm.my/?direktoristaf/direktori&kod=01285"],
          ["Nurani", "https://blogger.googleusercontent.com/img/b/R29vZ2xl/AVvXsEjTQqce9GtlFtrZEN3pbg1t2a3si7rAfDPA9Wqptuhd416jw8zafI_EQNLU5S2XEtXS0zGW6Vb_FWj-z0Jprlt7B-0qhQmP16K-SGl2ZEhWtrqGVtdAUWTcWRje-SaiMe3vdPA3myCS1Vrd/s1600/2.jpg", "Nurtures compassion and focus for future healthcare professionals.", "https://directory.usm.my/?direktoristaf/direktori&kod=01285"]
        ];
        foreach ($health as $h) {
          echo "
          <div class='col-md-4 col-lg-3'>
            <div class='card h-100'>
              <img src='{$h[1]}' alt='{$h[0]}'>
              <div class='card-body text-center'>
                <h5 class='card-title'>Desasiswa {$h[0]}</h5>
                <p class='card-text'>{$h[2]}</p>
                <a href='{$h[3]}' target='_blank' class='btn btn-sm'>View Details</a>
              </div>
            </div>
          </div>";
        }

        ?>
      </div>
    </div>
  </section>

  <!-- ABOUT -->
  <section id="about" class="container my-5 text-center">
    <h2 class="section-title">About eDesaCare</h2>
    <p class="px-md-5">
      <strong>USM eDesaCare</strong> is an integrated digital platform developed to enhance communication and service efficiency across all <em>Desasiswa</em> in Universiti Sains Malaysia. 
      The system empowers students to report, monitor, and resolve hostel-related issues with ease, ensuring a transparent and responsive feedback process. 
      By bridging the gap between residents and management, eDesaCare promotes accountability, improves living conditions, and cultivates a supportive campus community where every voice is valued and every concern leads to action.
    </p>
  </section>

  <!-- CONTACT -->
  <section id="contact" class="container text-center my-5">
    <h2 class="section-title">Contact Us</h2>
    <p>📍 Universiti Sains Malaysia, 11800 Pulau Pinang, Malaysia</p>
    <p>📞 +604-653 3888  |  ✉️ edesa@usm.my</p>
    <a href="https://www.usm.my/en/" target="_blank" class="btn-gradient">Visit Official USM Website</a>
  </section>

  <!-- FOOTER -->
  <footer>
    <p>© 2025 Universiti Sains Malaysia | USM eDesaCare | All Rights Reserved</p>
  </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

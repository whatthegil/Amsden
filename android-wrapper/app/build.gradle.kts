plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
}

android {
    namespace = "ph.edu.cspc.cbams"
    compileSdk = 34

    defaultConfig {
        applicationId = "ph.edu.cspc.cbams"
        minSdk = 24
        targetSdk = 34
        versionCode = 1
        versionName = "1.0"

        // The archive this build points at. Change here rather than in code, and
        // note that the Cloud domain is derived from the Laravel Cloud app and
        // environment names — renaming either changes this URL.
        buildConfigField("String", "ARCHIVE_URL", "\"https://c-bams-production-ku6t8w.laravel.cloud\"")
        buildConfigField("String", "ARCHIVE_HOST", "\"c-bams-production-ku6t8w.laravel.cloud\"")
    }

    buildFeatures {
        buildConfig = true
    }

    buildTypes {
        release {
            isMinifyEnabled = false
        }
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    kotlinOptions {
        jvmTarget = "17"
    }
}

dependencies {
    implementation("androidx.appcompat:appcompat:1.7.0")
    implementation("androidx.activity:activity-ktx:1.9.2")
    implementation("androidx.webkit:webkit:1.11.0")
}

allprojects {
    repositories {
        google()
        mavenCentral()
    }
}

val newBuildDir: Directory =
    rootProject.layout.buildDirectory
        .dir("../../build")
        .get()
rootProject.layout.buildDirectory.value(newBuildDir)

subprojects {
    val newSubprojectBuildDir: Directory = newBuildDir.dir(project.name)
    project.layout.buildDirectory.value(newSubprojectBuildDir)
}
// Algunos plugins (p. ej. connectivity_plus 5.x) se compilan contra Android 33, pero las
// librerías androidx que usan ahora exigen 34 o más y el build release falla en
// checkReleaseAarMetadata. Se sube el compileSdk de los plugins (no el de la app).
// Debe ir ANTES de evaluationDependsOn.
subprojects {
    afterEvaluate {
        val android = extensions.findByName("android")
        if (project.name != "app" && android is com.android.build.gradle.BaseExtension) {
            val actual = android.compileSdkVersion?.removePrefix("android-")?.toIntOrNull() ?: 0
            if (actual < 35) {
                android.compileSdkVersion(35)
            }
        }
    }
}
subprojects {
    project.evaluationDependsOn(":app")
}

tasks.register<Delete>("clean") {
    delete(rootProject.layout.buildDirectory)
}

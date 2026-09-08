package ph.edu.cspc.cbams

import android.app.Activity
import android.app.Application
import android.os.Bundle
import android.view.WindowManager

/**
 * Applies FLAG_SECURE to every window this app opens.
 *
 * MainActivity sets the flag itself, but the flag is per-window, not per-app:
 * anything that opens its own window - another activity added later, a dialog
 * hosted in one, a permission or file-picker screen - starts unprotected unless
 * it asks. Relying on each one to remember is the obvious way for a screenshot
 * to get through a build that looks correct.
 *
 * Registering here means a window cannot be added to this app without the flag,
 * including by someone extending it later who has never read MainActivity.
 */
class CbamsApplication : Application() {

    override fun onCreate() {
        super.onCreate()

        registerActivityLifecycleCallbacks(object : Application.ActivityLifecycleCallbacks {
            override fun onActivityCreated(activity: Activity, savedInstanceState: Bundle?) {
                activity.window.setFlags(
                    WindowManager.LayoutParams.FLAG_SECURE,
                    WindowManager.LayoutParams.FLAG_SECURE,
                )
            }

            override fun onActivityStarted(activity: Activity) = Unit
            override fun onActivityResumed(activity: Activity) = Unit
            override fun onActivityPaused(activity: Activity) = Unit
            override fun onActivityStopped(activity: Activity) = Unit
            override fun onActivitySaveInstanceState(activity: Activity, outState: Bundle) = Unit
            override fun onActivityDestroyed(activity: Activity) = Unit
        })
    }
}

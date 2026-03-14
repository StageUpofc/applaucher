package com.gb.launcher.util

import android.content.Context
import androidx.core.content.edit

object PrefsManager {
    private const val PREFS_NAME   = "gb_launcher_prefs"
    private const val KEY_API_URL  = "api_url"
    private const val DEFAULT_URL  = "https://teste-launcher.u-cdn.xyz/admin/api.php"

    fun getApiUrl(ctx: Context): String =
        ctx.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            .getString(KEY_API_URL, DEFAULT_URL) ?: DEFAULT_URL

    fun setApiUrl(ctx: Context, url: String) {
        ctx.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE).edit {
            putString(KEY_API_URL, url)
        }
        ApiClient.reset() // força recriação do Retrofit com nova URL
    }

    fun isApiUrlSet(ctx: Context): Boolean =
        getApiUrl(ctx) != DEFAULT_URL
}

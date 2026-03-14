package com.gb.launcher.data.model

import com.google.gson.annotations.SerializedName

/**
 * Modelos de dados da API do GB Launcher
 */

data class ApiResponse(
    @SerializedName("success")    val success: Boolean,
    @SerializedName("timestamp") val timestamp: Long,
    @SerializedName("settings")  val settings: LauncherSettings?,
    @SerializedName("apps")      val apps: List<AppItem>?,
    @SerializedName("categories") val categories: List<Category>?,
    @SerializedName("banners")   val banners: List<Banner>?,
    @SerializedName("total_apps") val totalApps: Int = 0
)

data class LauncherSettings(
    @SerializedName("logo_url")       val logoUrl: String?,
    @SerializedName("wallpaper_url")  val wallpaperUrl: String?,
    @SerializedName("launcher_title") val launcherTitle: String?,
    @SerializedName("primary_color")  val primaryColor: String?,
    @SerializedName("accent_color")   val accentColor: String?
)

data class AppItem(
    @SerializedName("id")           val id: Int,
    @SerializedName("name")         val name: String,
    @SerializedName("package_name") val packageName: String,
    @SerializedName("icon_url")     val iconUrl: String?,
    @SerializedName("category")     val category: String?,
    @SerializedName("description")  val description: String?,
    @SerializedName("position")     val position: Int,
    @SerializedName("is_pinned")    val isPinned: Boolean,
    // Estado local
    var isInstalled: Boolean = false
)

data class Category(
    @SerializedName("slug") val slug: String,
    @SerializedName("name") val name: String,
    @SerializedName("icon") val icon: String?
)

data class Banner(
    @SerializedName("id")        val id: Int,
    @SerializedName("title")     val title: String?,
    @SerializedName("image_url") val imageUrl: String,
    @SerializedName("action")    val action: String?
)

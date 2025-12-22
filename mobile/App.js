import "react-native-gesture-handler";
import React, { useEffect, useMemo, useState } from "react";
import {
  StyleSheet,
  Text,
  View,
  Switch,
  Pressable,
  Alert,
  ScrollView,
  useColorScheme,
  TextInput,
  Platform,
  KeyboardAvoidingView,
} from "react-native";
import AsyncStorage from "@react-native-async-storage/async-storage";
import { StatusBar } from "expo-status-bar";
import { SafeAreaProvider, SafeAreaView } from "react-native-safe-area-context";
import * as Notifications from "expo-notifications";
import { NavigationContainer } from "@react-navigation/native";
import { createBottomTabNavigator } from "@react-navigation/bottom-tabs";
import { Ionicons } from "@expo/vector-icons";
import { Calendar } from "react-native-calendars";
import DateTimePicker from "@react-native-community/datetimepicker";

const NOTIFICATION_TYPES = [
  { id: "general", label: "General Updates" },
  { id: "events", label: "Event Alerts" },
  { id: "announcements", label: "Announcements" },
];

const LEAD_TIMES = [
  { id: "1", label: "1 minute before" },
  { id: "5", label: "5 minutes before" },
  { id: "30", label: "30 minutes before" },
  { id: "60", label: "1 hour before" },
  { id: "1440", label: "1 day before" },
  { id: "10080", label: "7 days before" },
];

const STORAGE_KEY = "ontherock.preferences.v1";
const Tab = createBottomTabNavigator();
const API_BASE_URL = "http://localhost:8080";

const MOCK_EVENTS = [
  {
    id: "evt-1",
    title: "Sunday Service",
    startsAt: "2025-01-05T10:00:00Z",
    type: "events",
  },
  {
    id: "evt-2",
    title: "Community Lunch",
    startsAt: "2025-01-05T13:00:00Z",
    type: "general",
  },
  {
    id: "evt-3",
    title: "Youth Gathering",
    startsAt: "2025-01-08T18:30:00Z",
    type: "events",
  },
  {
    id: "evt-4",
    title: "Volunteer Kickoff",
    startsAt: "2025-01-10T19:00:00Z",
    type: "announcements",
  },
];

Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowBanner: true,
    shouldShowList: true,
    shouldPlaySound: false,
    shouldSetBadge: false,
  }),
});

export default function App() {
  const colorScheme = useColorScheme();
  const theme = getTheme(colorScheme);
  const styles = getStyles(theme);
  const [selectedTypes, setSelectedTypes] = useState(new Set());
  const [selectedLeads, setSelectedLeads] = useState(new Set());
  const [eventView, setEventView] = useState("list");
  const [selectedDate, setSelectedDate] = useState(null);
  const [isLoaded, setIsLoaded] = useState(false);
  const [submitForm, setSubmitForm] = useState({
    name: "",
    email: "",
    phone: "",
    startsAt: null,
    isOrganizer: null,
    description: "",
    website: "",
  });
  const [submitStatus, setSubmitStatus] = useState("idle");

  const filteredEvents = useMemo(() => {
    const typeSet = selectedTypes.size > 0 ? selectedTypes : null;
    return MOCK_EVENTS.filter((event) =>
      typeSet ? typeSet.has(event.type) : true
    ).sort((a, b) => new Date(a.startsAt) - new Date(b.startsAt));
  }, [selectedTypes]);

  const eventsByDate = useMemo(() => {
    const map = new Map();
    filteredEvents.forEach((event) => {
      const dateKey = new Date(event.startsAt).toISOString().slice(0, 10);
      if (!map.has(dateKey)) {
        map.set(dateKey, []);
      }
      map.get(dateKey).push(event);
    });
    return map;
  }, [filteredEvents]);

  const markedDates = useMemo(() => {
    const marks = {};
    eventsByDate.forEach((_events, dateKey) => {
      marks[dateKey] = { marked: true, dotColor: theme.accent };
    });
    if (selectedDate) {
      marks[selectedDate] = {
        ...(marks[selectedDate] || {}),
        selected: true,
        selectedColor: theme.accent,
      };
    }
    return marks;
  }, [eventsByDate, selectedDate, theme.accent]);

  useEffect(() => {
    const load = async () => {
      try {
        const raw = await AsyncStorage.getItem(STORAGE_KEY);
        if (raw) {
          const parsed = JSON.parse(raw);
          setSelectedTypes(new Set(parsed.types || []));
          setSelectedLeads(new Set(parsed.leads || []));
        }
      } catch (error) {
        console.warn("Failed to load preferences", error);
      } finally {
        setIsLoaded(true);
      }
    };

    load();
  }, []);

  useEffect(() => {
    if (!isLoaded) {
      return;
    }

    const save = async () => {
      const payload = {
        types: Array.from(selectedTypes),
        leads: Array.from(selectedLeads),
      };

      try {
        await AsyncStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
      } catch (error) {
        console.warn("Failed to save preferences", error);
      }
    };

    save();
  }, [isLoaded, selectedTypes, selectedLeads]);

  const toggleType = (id) => {
    setSelectedTypes((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  };

  const toggleLead = (id) => {
    setSelectedLeads((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  };

  const handleSave = () => {
    Alert.alert("Saved", "Your notification preferences are updated.");
  };

  const handleTestNotification = async () => {
    const { status } = await Notifications.requestPermissionsAsync();
    if (status !== "granted") {
      Alert.alert(
        "Permission needed",
        "Enable notifications to receive alerts."
      );
      return;
    }

    await Notifications.scheduleNotificationAsync({
      content: {
        title: "OnTheRock Test",
        body: "This is a local test notification.",
      },
      trigger: { seconds: 10 },
    });

    Alert.alert("Scheduled", "Test notification will fire in 10 seconds.");
  };

  const updateSubmitForm = (key, value) => {
    setSubmitForm((prev) => ({ ...prev, [key]: value }));
  };

  const formatDateTime = (value) => {
    if (!value) {
      return "";
    }
    const pad = (num) => String(num).padStart(2, "0");
    return `${value.getFullYear()}-${pad(value.getMonth() + 1)}-${pad(
      value.getDate()
    )} ${pad(value.getHours())}:${pad(value.getMinutes())}`;
  };

  const handleSubmitEvent = async () => {
    if (submitStatus === "submitting") {
      return;
    }

    const requiredFields = [
      submitForm.name,
      submitForm.email,
      submitForm.description,
    ];

    if (requiredFields.some((value) => value.trim() === "")) {
      Alert.alert("Missing info", "Please fill all required fields.");
      return;
    }

    if (!submitForm.startsAt) {
      Alert.alert("Missing info", "Please choose a date and time.");
      return;
    }

    if (submitForm.isOrganizer === null) {
      Alert.alert("Missing info", "Please answer the organizer question.");
      return;
    }

    try {
      setSubmitStatus("submitting");
      const response = await fetch(`${API_BASE_URL}/api/event-submissions`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          name: submitForm.name.trim(),
          email: submitForm.email.trim(),
          phone: submitForm.phone.trim(),
          starts_at: formatDateTime(submitForm.startsAt),
          is_organizer: submitForm.isOrganizer,
          description: submitForm.description.trim(),
          website: submitForm.website.trim(),
        }),
      });

      if (!response.ok) {
        const payload = await response.json().catch(() => ({}));
        const message = payload.error || "Unable to submit event.";
        Alert.alert("Submission failed", message);
        setSubmitStatus("idle");
        return;
      }

      Alert.alert("Submitted", "Thanks! We received your event.");
      setSubmitForm({
        name: "",
        email: "",
        phone: "",
        startsAt: null,
        isOrganizer: null,
        description: "",
        website: "",
      });
      setSubmitStatus("idle");
    } catch (error) {
      Alert.alert("Submission failed", "Please try again later.");
      setSubmitStatus("idle");
    }
  };

  return (
    <SafeAreaProvider>
      <NavigationContainer>
        <StatusBar style={theme.statusBarStyle} />
        <Tab.Navigator
          screenOptions={({ route }) => ({
            headerShown: false,
            tabBarActiveTintColor: theme.tabActive,
            tabBarInactiveTintColor: theme.tabInactive,
            tabBarStyle: {
              backgroundColor: theme.tabBar,
              borderTopColor: theme.border,
              borderTopWidth: 1,
              height: 88,
              paddingBottom: 20,
              paddingTop: 10,
            },
            tabBarLabelStyle: {
              fontSize: 12,
              fontWeight: "600",
            },
            tabBarIcon: ({ color, size }) => {
              let iconName = "calendar-outline";
              if (route.name === "Settings") {
                iconName = "settings-outline";
              } else if (route.name === "Submit Event") {
                iconName = "add-circle-outline";
              }
              return <Ionicons name={iconName} size={size} color={color} />;
            },
          })}
        >
          <Tab.Screen name="Upcoming Events">
            {() => (
              <MainScreen
                styles={styles}
                eventView={eventView}
                setEventView={setEventView}
                filteredEvents={filteredEvents}
                markedDates={markedDates}
                selectedDate={selectedDate}
                setSelectedDate={setSelectedDate}
                eventsByDate={eventsByDate}
              />
            )}
          </Tab.Screen>
          <Tab.Screen name="Submit Event">
            {() => (
              <SubmitEventScreen
                styles={styles}
                submitForm={submitForm}
                submitStatus={submitStatus}
                updateSubmitForm={updateSubmitForm}
                handleSubmitEvent={handleSubmitEvent}
              />
            )}
          </Tab.Screen>
          <Tab.Screen name="Settings">
            {() => (
              <SettingsScreen
                styles={styles}
                selectedTypes={selectedTypes}
                selectedLeads={selectedLeads}
                toggleType={toggleType}
                toggleLead={toggleLead}
                handleSave={handleSave}
                handleTestNotification={handleTestNotification}
              />
            )}
          </Tab.Screen>
        </Tab.Navigator>
      </NavigationContainer>
    </SafeAreaProvider>
  );
}

function MainScreen({
  styles,
  eventView,
  setEventView,
  filteredEvents,
  markedDates,
  selectedDate,
  setSelectedDate,
  eventsByDate,
}) {
  const selectedEvents = selectedDate ? eventsByDate.get(selectedDate) || [] : [];
  const header = (
    <View style={eventView === "calendar" ? styles.calendarHeader : styles.header}>
      <Text style={styles.title}>OnTheRock</Text>
      <Text style={styles.subtitle}>
        Upcoming events curated for your notification choices.
      </Text>

      <View style={styles.segmentedControl}>
        <Pressable
          style={[
            styles.segmentButton,
            eventView === "list" && styles.segmentButtonActive,
          ]}
          onPress={() => setEventView("list")}
        >
          <Text
            style={[
              styles.segmentButtonText,
              eventView === "list" && styles.segmentButtonTextActive,
            ]}
          >
            List
          </Text>
        </Pressable>
        <Pressable
          style={[
            styles.segmentButton,
            eventView === "calendar" && styles.segmentButtonActive,
          ]}
          onPress={() => setEventView("calendar")}
        >
          <Text
            style={[
              styles.segmentButtonText,
              eventView === "calendar" && styles.segmentButtonTextActive,
            ]}
          >
            Calendar
          </Text>
        </Pressable>
      </View>
    </View>
  );

  if (eventView === "calendar") {
    return (
      <SafeAreaView style={styles.container} edges={["top"]}>
        <ScrollView contentContainerStyle={styles.content}>
          {header}
          <View style={styles.section}>
            <Calendar
              current={selectedDate || undefined}
              onDayPress={(day) => setSelectedDate(day.dateString)}
              markedDates={markedDates}
              monthFormat={"MMMM yyyy"}
              enableSwipeMonths
              hideExtraDays
              theme={{
                backgroundColor: "transparent",
                calendarBackground: styles.calendarSurface.backgroundColor,
                textSectionTitleColor: styles.calendarText.color,
                dayTextColor: styles.calendarText.color,
                monthTextColor: styles.calendarText.color,
                selectedDayTextColor: styles.calendarSelectedText.color,
                todayTextColor: styles.calendarTodayText.color,
                arrowColor: styles.calendarArrow.color,
                dotColor: styles.calendarDot.color,
                selectedDotColor: styles.calendarSelectedText.color,
              }}
            />
          </View>
          <View style={styles.section}>
            <Text style={styles.calendarDate}>
              {selectedDate
                ? new Date(selectedDate).toLocaleDateString(undefined, {
                    weekday: "long",
                    month: "short",
                    day: "numeric",
                  })
                : "Select a date"}
            </Text>
            {selectedEvents.length === 0 ? (
              <Text style={styles.emptyText}>
                No events scheduled for this date.
              </Text>
            ) : (
              selectedEvents.map((event) => (
                <View key={event.id} style={styles.calendarRow}>
                  <Text style={styles.eventTitle}>{event.title}</Text>
                  <Text style={styles.eventMeta}>
                    {new Date(event.startsAt).toLocaleTimeString([], {
                      hour: "2-digit",
                      minute: "2-digit",
                    })}
                  </Text>
                </View>
              ))
            )}
          </View>
        </ScrollView>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.container} edges={["top"]}>
      <ScrollView contentContainerStyle={styles.content}>
        {header}

        {filteredEvents.length === 0 ? (
          <View style={styles.emptyState}>
            <Text style={styles.emptyTitle}>No events yet</Text>
            <Text style={styles.emptyText}>
              Adjust your settings to see relevant events.
            </Text>
          </View>
        ) : (
          <View style={styles.section}>
            {filteredEvents.map((event) => (
              <View key={event.id} style={styles.eventRow}>
                <View>
                  <Text style={styles.eventTitle}>{event.title}</Text>
                  <Text style={styles.eventMeta}>
                    {new Date(event.startsAt).toLocaleString()}
                  </Text>
                </View>
                <View style={styles.eventTag}>
                  <Text style={styles.eventTagText}>{event.type}</Text>
                </View>
              </View>
            ))}
          </View>
        )}
      </ScrollView>
    </SafeAreaView>
  );
}

function SettingsScreen({
  styles,
  selectedTypes,
  selectedLeads,
  toggleType,
  toggleLead,
  handleSave,
  handleTestNotification,
}) {
  return (
    <SafeAreaView style={styles.container} edges={["top"]}>
      <ScrollView contentContainerStyle={styles.content}>
        <Text style={styles.title}>Settings</Text>
        <Text style={styles.subtitle}>
          Choose which notifications you want and when you want them.
        </Text>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Notification Types</Text>
          {NOTIFICATION_TYPES.map((type) => (
            <View key={type.id} style={styles.row}>
              <Text style={styles.label}>{type.label}</Text>
              <Switch
                value={selectedTypes.has(type.id)}
                onValueChange={() => toggleType(type.id)}
              />
            </View>
          ))}
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>When should we notify you?</Text>
          {LEAD_TIMES.map((lead) => (
            <View key={lead.id} style={styles.row}>
              <Text style={styles.label}>{lead.label}</Text>
              <Switch
                value={selectedLeads.has(lead.id)}
                onValueChange={() => toggleLead(lead.id)}
              />
            </View>
          ))}
        </View>

        <Pressable style={styles.button} onPress={handleSave}>
          <Text style={styles.buttonText}>Save Preferences</Text>
        </Pressable>
        <Pressable
          style={[styles.button, styles.secondaryButton]}
          onPress={handleTestNotification}
        >
          <Text style={[styles.buttonText, styles.secondaryButtonText]}>
            Send Test Notification
          </Text>
        </Pressable>

        <View style={styles.note}>
          <Text style={styles.noteText}>
            Push notifications will be delivered through OneSignal once your app
            keys are configured.
          </Text>
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

function SubmitEventScreen({
  styles,
  submitForm,
  submitStatus,
  updateSubmitForm,
  handleSubmitEvent,
}) {
  const [showDatePicker, setShowDatePicker] = useState(false);
  const formattedDateTime = submitForm.startsAt
    ? new Date(submitForm.startsAt).toLocaleString()
    : "";

  const handleDateChange = (_event, selectedDate) => {
    if (Platform.OS !== "ios") {
      setShowDatePicker(false);
    }
    if (selectedDate) {
      updateSubmitForm("startsAt", selectedDate);
    }
  };

  return (
    <SafeAreaView style={styles.container} edges={["top"]}>
      <KeyboardAvoidingView
        behavior={Platform.OS === "ios" ? "padding" : undefined}
        style={styles.flex}
      >
        <ScrollView
          contentContainerStyle={styles.content}
          keyboardShouldPersistTaps="handled"
          keyboardDismissMode={Platform.OS === "ios" ? "interactive" : "on-drag"}
        >
          <Text style={styles.title}>Submit Event</Text>
          <Text style={styles.subtitle}>
            Share the details and we will review your request.
          </Text>

          <View style={styles.section}>
          <Text style={styles.sectionTitle}>Your Name *</Text>
          <TextInput
            style={styles.input}
            placeholder="Jane Doe"
            placeholderTextColor={styles.inputPlaceholder.color}
            value={submitForm.name}
            onChangeText={(value) => updateSubmitForm("name", value)}
          />

          <Text style={styles.sectionTitle}>Your Email Address *</Text>
          <TextInput
            style={styles.input}
            placeholder="you@example.com"
            placeholderTextColor={styles.inputPlaceholder.color}
            keyboardType="email-address"
            autoCapitalize="none"
            value={submitForm.email}
            onChangeText={(value) => updateSubmitForm("email", value)}
          />

          <Text style={styles.sectionTitle}>Your Phone Number</Text>
          <TextInput
            style={styles.input}
            placeholder="(01534) 123456"
            placeholderTextColor={styles.inputPlaceholder.color}
            keyboardType="phone-pad"
            value={submitForm.phone}
            onChangeText={(value) => updateSubmitForm("phone", value)}
          />

          <Text style={styles.sectionTitle}>Date/Time of Event *</Text>
          <Pressable
            style={styles.input}
            onPress={() => setShowDatePicker(true)}
          >
            <Text
              style={
                formattedDateTime
                  ? styles.inputText
                  : styles.inputPlaceholder
              }
            >
              {formattedDateTime || "Select date and time"}
            </Text>
          </Pressable>
          {showDatePicker && (
            <View style={styles.datePickerContainer}>
              <DateTimePicker
                value={submitForm.startsAt || new Date()}
                mode="datetime"
                display={Platform.OS === "ios" ? "spinner" : "default"}
                onChange={handleDateChange}
              />
              {Platform.OS === "ios" && (
                <Pressable
                  style={styles.datePickerDone}
                  onPress={() => setShowDatePicker(false)}
                >
                  <Text style={styles.datePickerDoneText}>Done</Text>
                </Pressable>
              )}
            </View>
          )}

          <Text style={styles.sectionTitle}>Are you running this event? *</Text>
          <View style={styles.segmentedControl}>
            <Pressable
              style={[
                styles.segmentButton,
                submitForm.isOrganizer === true && styles.segmentButtonActive,
              ]}
              onPress={() => updateSubmitForm("isOrganizer", true)}
            >
              <Text
                style={[
                  styles.segmentButtonText,
                  submitForm.isOrganizer === true &&
                    styles.segmentButtonTextActive,
                ]}
              >
                Yes
              </Text>
            </Pressable>
            <Pressable
              style={[
                styles.segmentButton,
                submitForm.isOrganizer === false && styles.segmentButtonActive,
              ]}
              onPress={() => updateSubmitForm("isOrganizer", false)}
            >
              <Text
                style={[
                  styles.segmentButtonText,
                  submitForm.isOrganizer === false &&
                    styles.segmentButtonTextActive,
                ]}
              >
                No
              </Text>
            </Pressable>
          </View>
          <View style={styles.fieldSpacer} />

          <Text style={styles.sectionTitle}>
            Please give a short description of the event *
          </Text>
          <TextInput
            style={[styles.input, styles.inputMultiline]}
            placeholder="Tell us about the event..."
            placeholderTextColor={styles.inputPlaceholder.color}
            multiline
            numberOfLines={4}
            textAlignVertical="top"
            value={submitForm.description}
            onChangeText={(value) => updateSubmitForm("description", value)}
          />

          <Text style={styles.sectionTitle}>Website/Link to event</Text>
          <TextInput
            style={styles.input}
            placeholder="https://"
            placeholderTextColor={styles.inputPlaceholder.color}
            autoCapitalize="none"
            value={submitForm.website}
            onChangeText={(value) => updateSubmitForm("website", value)}
          />
          </View>

          <Pressable
            style={styles.button}
            onPress={handleSubmitEvent}
            disabled={submitStatus === "submitting"}
          >
            <Text style={styles.buttonText}>
              {submitStatus === "submitting" ? "Submitting..." : "Submit"}
            </Text>
          </Pressable>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}
const getTheme = (scheme) => {
  const isDark = scheme === "dark";
  return {
    statusBarStyle: isDark ? "light" : "dark",
    background: isDark ? "#12100D" : "#F6F5F1",
    surface: isDark ? "#1F1B16" : "#FFFFFF",
    surfaceAlt: isDark ? "#1A1713" : "#F6F0DD",
    textPrimary: isDark ? "#F7F2E8" : "#2E2A24",
    textSecondary: isDark ? "#B8AE9E" : "#6A6256",
    textMuted: isDark ? "#9B9285" : "#7A6E5B",
    border: isDark ? "#2A241E" : "#EFEAE0",
    accent: isDark ? "#E0B54C" : "#C9A227",
    accentText: isDark ? "#1F1606" : "#1F1A12",
    tagBackground: isDark ? "#2A2318" : "#F6F0DD",
    tagText: isDark ? "#F1D48D" : "#7A5A00",
    noteBackground: isDark ? "#2A2214" : "#FFF4D6",
    noteText: isDark ? "#F4D38A" : "#6A4E00",
    tabBar: isDark ? "#16130F" : "#FFFFFF",
    tabActive: isDark ? "#F7E4B4" : "#2E2A24",
    tabInactive: isDark ? "#8F8678" : "#8A7E6C",
  };
};

const getStyles = (theme) =>
  StyleSheet.create({
    container: {
      flex: 1,
      backgroundColor: theme.background,
    },
    content: {
      padding: 24,
      gap: 20,
    },
    title: {
      fontSize: 32,
      fontWeight: "700",
      color: theme.textPrimary,
    },
    subtitle: {
      fontSize: 16,
      color: theme.textSecondary,
    },
    section: {
      backgroundColor: theme.surface,
      borderRadius: 16,
      padding: 16,
      shadowColor: "#000",
      shadowOpacity: 0.08,
      shadowRadius: 10,
      shadowOffset: { width: 0, height: 4 },
      elevation: 2,
    },
    sectionTitle: {
      fontSize: 18,
      fontWeight: "600",
      color: theme.textPrimary,
      marginBottom: 12,
    },
    row: {
      flexDirection: "row",
      justifyContent: "space-between",
      alignItems: "center",
      paddingVertical: 8,
    },
    eventRow: {
      flexDirection: "row",
      justifyContent: "space-between",
      alignItems: "center",
      paddingVertical: 12,
      borderBottomWidth: 1,
      borderBottomColor: theme.border,
    },
    eventTitle: {
      fontSize: 16,
      fontWeight: "600",
      color: theme.textPrimary,
    },
    eventMeta: {
      fontSize: 13,
      color: theme.textSecondary,
      marginTop: 2,
    },
    eventTag: {
      backgroundColor: theme.tagBackground,
      paddingHorizontal: 10,
      paddingVertical: 4,
      borderRadius: 12,
    },
    eventTagText: {
      fontSize: 12,
      color: theme.tagText,
      textTransform: "capitalize",
    },
    header: {
      gap: 20,
    },
    calendarHeader: {
      gap: 20,
    },
    calendarSurface: {
      backgroundColor: theme.surface,
    },
    calendarDate: {
      fontSize: 14,
      fontWeight: "600",
      color: theme.textSecondary,
      marginBottom: 8,
    },
    calendarRow: {
      paddingVertical: 8,
      borderBottomWidth: 1,
      borderBottomColor: theme.border,
    },
    calendarText: {
      color: theme.textPrimary,
    },
    calendarSelectedText: {
      color: theme.accentText,
    },
    calendarTodayText: {
      color: theme.accent,
    },
    calendarArrow: {
      color: theme.textSecondary,
    },
    calendarDot: {
      color: theme.accent,
    },
    label: {
      fontSize: 16,
      color: theme.textPrimary,
    },
    segmentedControl: {
      flexDirection: "row",
      backgroundColor: theme.border,
      borderRadius: 18,
      padding: 4,
    },
    segmentButton: {
      flex: 1,
      paddingVertical: 8,
      borderRadius: 14,
      alignItems: "center",
    },
    segmentButtonActive: {
      backgroundColor: theme.surface,
    },
    segmentButtonText: {
      fontSize: 14,
      fontWeight: "600",
      color: theme.textMuted,
    },
    segmentButtonTextActive: {
      color: theme.textPrimary,
    },
    input: {
      borderWidth: 1,
      borderColor: theme.border,
      borderRadius: 12,
      paddingHorizontal: 12,
      paddingVertical: 10,
      fontSize: 15,
      color: theme.textPrimary,
      marginBottom: 16,
      backgroundColor: theme.surface,
    },
    inputText: {
      fontSize: 15,
      color: theme.textPrimary,
    },
    inputMultiline: {
      minHeight: 120,
    },
    inputPlaceholder: {
      color: theme.textMuted,
    },
    datePickerContainer: {
      marginBottom: 16,
    },
    datePickerDone: {
      alignSelf: "center",
      marginTop: 12,
      paddingVertical: 10,
      paddingHorizontal: 24,
      borderRadius: 999,
      backgroundColor: theme.accent,
    },
    datePickerDoneText: {
      color: theme.accentText,
      fontSize: 15,
      fontWeight: "700",
    },
    fieldSpacer: {
      height: 10,
    },
    flex: {
      flex: 1,
    },
    emptyState: {
      backgroundColor: theme.surface,
      borderRadius: 16,
      padding: 24,
      alignItems: "center",
    },
    emptyTitle: {
      fontSize: 18,
      fontWeight: "600",
      color: theme.textPrimary,
    },
    emptyText: {
      fontSize: 14,
      color: theme.textSecondary,
      marginTop: 6,
      textAlign: "center",
    },
    button: {
      backgroundColor: theme.accent,
      paddingVertical: 14,
      borderRadius: 30,
      alignItems: "center",
    },
    secondaryButton: {
      backgroundColor: theme.surfaceAlt,
    },
    buttonText: {
      color: theme.accentText,
      fontWeight: "600",
      fontSize: 16,
    },
    secondaryButtonText: {
      color: theme.tagText,
    },
    note: {
      backgroundColor: theme.noteBackground,
      padding: 12,
      borderRadius: 12,
    },
    noteText: {
      color: theme.noteText,
      fontSize: 13,
    },
  });
